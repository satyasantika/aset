<?php

use App\Actions\Pemeliharaan\TerimaLaporanKerusakan;
use App\Enums\StatusAset;
use App\Enums\StatusTiket;
use App\Events\LaporanKerusakanDiterima;
use App\Http\Controllers\Publik\LaporKerusakanController;
use App\Livewire\Pindai;
use App\Models\Aset;
use App\Models\Ruangan;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use App\Support\HashIp;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    RateLimiter::clear('lapor');
    Cache::flush();
});

function lapor(Aset $aset, array $data = [], string $ip = '203.0.113.7')
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post('/lapor-kerusakan/'.$aset->id, ['deskripsi' => 'Kaki kursi patah', ...$data]);
}

it('menampilkan formulir hanya dengan data barang dasar, tanpa data pribadi, noindex, dengan honeypot dan CSRF', function () {
    $pic = User::factory()->create(['name' => 'Rahmat Laboran']);
    $r = Ruangan::query()->create(['kode' => 'R-1', 'nama' => 'Lab Komputer']);
    $r->pic()->attach($pic->id, ['utama' => true]);
    $aset = Aset::factory()->diRuangan($r)->create(['nama' => 'Kursi Lab', 'merk_tipe' => 'Chitose', 'nilai_perolehan' => '987654321.00']);

    $respons = $this->get('/lapor-kerusakan/'.$aset->id);

    $respons->assertOk()->assertSee('Laporkan kerusakan')->assertSee('Kursi Lab')->assertSee('Lab Komputer')->assertSee('name="_token"', false)
        ->assertSee('name="'.LaporKerusakanController::HONEYPOT.'"', false)
        ->assertDontSee('Rahmat')->assertDontSee('987654321')->assertDontSee($pic->email);
    expect($respons->headers->get('X-Robots-Tag'))->toContain('noindex')->and($respons->getContent())->toContain('noindex, nofollow');
});

it('formulir untuk barang tidak ada atau sudah dihapus mengembalikan 404 ramah', function () {
    $hapus = Aset::factory()->create(['status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK', 'tanggal_sk_penghapusan' => '2026-01-01']);

    $this->get('/lapor-kerusakan/'.$hapus->id)->assertNotFound()->assertSee('tidak ditemukan');
    $this->get('/lapor-kerusakan/'.fake()->uuid())->assertNotFound();
    $this->get('/lapor-kerusakan/bukan-uuid')->assertNotFound();
    lapor($hapus)->assertNotFound();
    expect(TiketPemeliharaan::count())->toBe(0);
});

it('laporan sah membuat tiket baru bagi PIC dan memancarkan event, tanpa mengubah status aset (US-PML-01)', function () {
    Event::fake([LaporanKerusakanDiterima::class]);
    $aset = Aset::factory()->create();

    $respons = lapor($aset, ['nama' => 'Mahasiswa A', 'kontak' => '0812345']);

    $tiket = TiketPemeliharaan::query()->firstOrFail();
    $respons->assertRedirect('/lapor-kerusakan/'.$aset->id);
    expect($tiket)->aset_id->toBe($aset->id)->status->toBe(StatusTiket::Baru)->sumber->toBe('publik')->deskripsi->toBe('Kaki kursi patah')
        ->nama_pelapor->toBe('Mahasiswa A')->kontak_pelapor->toBe('0812345')->pelapor_user_id->toBeNull()
        ->and($aset->fresh()->status)->toBe(StatusAset::Aktif);
    Event::assertDispatched(LaporanKerusakanDiterima::class, fn ($e) => $e->tiket->is($tiket));

    $this->get('/lapor-kerusakan/'.$aset->id)->assertSee('Terima kasih')->assertSee($tiket->nomor);
});

it('nama dan kontak bersifat opsional', function () {
    $aset = Aset::factory()->create();

    lapor($aset)->assertRedirect();

    expect(TiketPemeliharaan::first())->nama_pelapor->toBeNull()->kontak_pelapor->toBeNull();
});

it('tidak menyimpan IP mentah di mana pun; hanya HMAC harian 64 heks (BR-12)', function () {
    $aset = Aset::factory()->create();

    lapor($aset, ip: '203.0.113.7');

    $tiket = TiketPemeliharaan::first();
    expect($tiket->ip_hash)->toHaveLength(64)->toMatch('/^[0-9a-f]{64}$/')->and($tiket->ip_hash)->toBe(HashIp::dari('203.0.113.7'));

    $semua = json_encode([
        DB::table('tiket_pemeliharaan')->get(), DB::table('activity_log')->get(), DB::table('aset')->get(), DB::table('sessions')->get(),
    ]);
    expect($semua)->not->toContain('203.0.113.7')->not->toContain('203\\/0\\/113');
});

it('hash IP stabil dalam sehari, berbeda antar IP, dan berganti saat garam harian berganti', function () {
    $a = HashIp::dari('198.51.100.1');

    expect(HashIp::dari('198.51.100.1'))->toBe($a)
        ->and(HashIp::dari('198.51.100.2'))->not->toBe($a);

    $this->travel(2)->days();
    expect(HashIp::dari('198.51.100.1'))->not->toBe($a);
    $this->travelBack();

    expect(Cache::has('aset:lapor:garam:'.now()->toDateString()))->toBeTrue();
});

it('audit tiket tidak memuat hash IP', function () {
    $aset = Aset::factory()->create();
    lapor($aset);

    $properti = DB::table('activity_log')->where('log_name', 'tiketpemeliharaan')->pluck('properties')->implode(' ');

    expect($properti)->not->toContain(TiketPemeliharaan::first()->ip_hash)->not->toContain('ip_hash');
});

it('membatasi 5 laporan per jam per IP: laporan ke-6 → 429, IP lain tetap boleh', function () {
    $aset = Aset::factory()->create();

    foreach (range(1, 5) as $i) {
        lapor($aset, ['deskripsi' => "Laporan nomor {$i}"], '198.51.100.9')->assertRedirect();
    }

    lapor($aset, ['deskripsi' => 'Laporan keenam'], '198.51.100.9')->assertStatus(429);
    expect(TiketPemeliharaan::count())->toBe(5);

    lapor($aset, ['deskripsi' => 'Dari IP lain'], '198.51.100.10')->assertRedirect();
    expect(TiketPemeliharaan::count())->toBe(6);

    $this->travel(61)->minutes();
    lapor($aset, ['deskripsi' => 'Sesudah satu jam'], '198.51.100.9')->assertRedirect();
});

it('menampilkan formulir tidak menghabiskan jatah laporan', function () {
    $aset = Aset::factory()->create();

    foreach (range(1, 10) as $i) {
        $this->get('/lapor-kerusakan/'.$aset->id)->assertOk();
    }

    lapor($aset)->assertRedirect();
});

it('honeypot terisi diabaikan: tampak berhasil tetapi tidak ada tiket', function () {
    $aset = Aset::factory()->create();

    lapor($aset, [LaporKerusakanController::HONEYPOT => 'http://spam.example'])->assertRedirect('/lapor-kerusakan/'.$aset->id);

    expect(TiketPemeliharaan::count())->toBe(0);
    $this->get('/lapor-kerusakan/'.$aset->id)->assertSee('Terima kasih');
});

it('memvalidasi deskripsi, nama, dan kontak', function (array $data, string $kolom) {
    $aset = Aset::factory()->create();

    lapor($aset, $data)->assertSessionHasErrors($kolom);
    expect(TiketPemeliharaan::count())->toBe(0);
})->with([
    'deskripsi kosong' => [['deskripsi' => ''], 'deskripsi'],
    'deskripsi terlalu pendek' => [['deskripsi' => 'rsk'], 'deskripsi'],
    'deskripsi terlalu panjang' => [['deskripsi' => str_repeat('x', 2001)], 'deskripsi'],
    'nama terlalu panjang' => [['nama' => str_repeat('n', 151)], 'nama'],
    'kontak terlalu panjang' => [['kontak' => str_repeat('1', 51)], 'kontak'],
]);

it('Action menolak aset dihapus dan memotong nama/kontak', function () {
    $hapus = Aset::factory()->create(['status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK', 'tanggal_sk_penghapusan' => '2026-01-01']);

    expect(fn () => app(TerimaLaporanKerusakan::class)->handle($hapus, 'Rusak sekali', null, null, '127.0.0.1'))->toThrow(ValidationException::class);

    $tiket = app(TerimaLaporanKerusakan::class)->handle(Aset::factory()->create(), "  Rusak berat  \n", '  ', '  ', null);
    expect($tiket)->deskripsi->toBe('Rusak berat')->nama_pelapor->toBeNull()->kontak_pelapor->toBeNull()->ip_hash->toBeNull();
});

it('civitas login melapor dari /pindai: sumber civitas, pelapor tercatat, tanpa hash IP', function () {
    Event::fake([LaporanKerusakanDiterima::class]);
    $aset = Aset::factory()->create(['nama' => 'Proyektor Aula']);
    $civitas = User::factory()->create(['name' => 'Dr. Rina']);
    $civitas->assignRole('civitas');
    $this->actingAs($civitas);

    Livewire::test(Pindai::class)
        ->set('teks', $aset->id)->call('cari')
        ->assertSee('Laporkan kerusakan')
        ->set('deskripsiKerusakan', 'Lampu proyektor mati')->call('laporKerusakan')
        ->assertHasNoErrors()->assertSee('Laporan diterima sebagai tiket TKT-');

    $tiket = TiketPemeliharaan::first();
    expect($tiket)->sumber->toBe('civitas')->pelapor_user_id->toBe($civitas->id)->nama_pelapor->toBe('Dr. Rina')->ip_hash->toBeNull()
        ->deskripsi->toBe('Lampu proyektor mati');
    Event::assertDispatched(LaporanKerusakanDiterima::class);
});

it('staf PIC yang melapor dari /pindai tercatat sebagai sumber pic', function () {
    $r = Ruangan::query()->create(['kode' => 'R-1', 'nama' => 'Lab']);
    $pic = User::factory()->create();
    $pic->assignRole('pic-ruangan');
    $pic->ruanganDikelola()->attach($r->id);
    $aset = Aset::factory()->diRuangan($r)->create();
    $this->actingAs($pic);

    Livewire::test(Pindai::class)->set('teks', $aset->id)->call('cari')->set('deskripsiKerusakan', 'Retak di sisi kiri')->call('laporKerusakan')->assertHasNoErrors();

    expect(TiketPemeliharaan::first()->sumber)->toBe('pic');
});

it('/pindai memvalidasi deskripsi dan membatasi 10 laporan per jam per pengguna', function () {
    $aset = Aset::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('civitas');
    $this->actingAs($user);

    Livewire::test(Pindai::class)->set('teks', $aset->id)->call('cari')->set('deskripsiKerusakan', 'x')->call('laporKerusakan')->assertHasErrors('deskripsiKerusakan');

    $komponen = Livewire::test(Pindai::class)->set('teks', $aset->id)->call('cari');
    foreach (range(1, 10) as $i) {
        $komponen->set('deskripsiKerusakan', "Laporan nomor {$i}")->call('laporKerusakan')->assertHasNoErrors();
    }
    $komponen->set('deskripsiKerusakan', 'Laporan kesebelas')->call('laporKerusakan')->assertHasErrors('deskripsiKerusakan');

    expect(TiketPemeliharaan::count())->toBe(10);
});

it('rute pengiriman memakai throttle lapor dan tampilan memakai throttle lookup', function () {
    $router = app('router')->getRoutes();

    expect($router->getByName('publik.lapor.kirim')->gatherMiddleware())->toContain('throttle:lapor')
        ->and($router->getByName('publik.lapor')->gatherMiddleware())->toContain('throttle:lookup');
});
