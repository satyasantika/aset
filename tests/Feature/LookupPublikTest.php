<?php

use App\Actions\Label\ResolusiLabel;
use App\Enums\KondisiAset;
use App\Livewire\Pindai;
use App\Models\Aset;
use App\Models\KodefikasiBarang;
use App\Models\LabelLama;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\FormatLabelLama;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    RateLimiter::clear('lookup');
});

function asetDenganLabelLama(array $label, array $atribut = []): Aset
{
    $aset = Aset::factory()->create($atribut);
    foreach ($label as $teks) {
        LabelLama::query()->create(['teks' => $teks, 'aset_id' => $aset->id]);
    }

    return $aset;
}

it('menghasilkan kandidat label lama dalam urutan SIMAN-2', function (string $masukan, array $harapan) {
    expect(FormatLabelLama::kandidat($masukan))->toBe($harapan);
})->with([
    'kategori-kode-unit' => ['mbl-935464-1', ['MBL-935464-1', '935464-1', '935464', 'MBL-1', 'MBL']],
    'kategori-kode-unit>1' => ['MBL-935464-3', ['MBL-935464-3', '935464-3', 'MBL-3']],
    'kode-unit' => ['935464-2', ['935464-2']],
    'kode-unit 1' => ['935464-1', ['935464-1', '935464']],
    'kode tunggal' => ['935464', ['935464']],
    'dengan spasi' => ['  nup-12 ', ['NUP-12']],
    'unit bukan angka' => ['AB-CD', ['AB-CD', 'AB-1', 'AB']],
    'kosong' => ['   ', []],
]);

it('meresolusi label lama MBL-935464-1 lewat label_lama dan mengalihkan ke /a/{id}', function () {
    $aset = asetDenganLabelLama(['MBL-935464-1', '935464-1', '935464']);

    $this->get('/l/MBL-935464-1')->assertRedirect('/a/'.$aset->id)->assertStatus(302);
    $this->get('/l/mbl-935464-1')->assertRedirect('/a/'.$aset->id);
    $this->get('/l/935464')->assertRedirect('/a/'.$aset->id);
});

it('mengutamakan label yang paling spesifik bila ada beberapa kandidat', function () {
    $induk = asetDenganLabelLama(['935464-1']);
    $unit = asetDenganLabelLama(['MBL-935464-2', '935464-2']);

    expect(app(ResolusiLabel::class)->handle('MBL-935464-2')->id)->toBe($unit->id)
        ->and(app(ResolusiLabel::class)->handle('935464-1')->id)->toBe($induk->id);
});

it('tidak jatuh ke unit 1 bila unit yang dipindai tidak terdaftar (R-17)', function () {
    asetDenganLabelLama(['935464-1', '935464']);

    expect(app(ResolusiLabel::class)->handle('935464-5'))->toBeNull();
    $this->get('/l/935464-5')->assertNotFound();
});

it('label lama tidak ditemukan menampilkan 404 ramah tanpa indeks', function () {
    $respons = $this->get('/l/TIDAK-ADA-9');

    $respons->assertNotFound()->assertSee('Data barang tidak ditemukan')->assertSee('dicetak ulang');
    expect($respons->headers->get('X-Robots-Tag'))->toContain('noindex');
});

it('lookup /a/{id} hanya menampilkan field putih BR-18', function () {
    $pic = User::factory()->create(['name' => 'Rahmat Laboran Rahasia']);
    $ruangan = Ruangan::query()->create(['kode' => 'R-LAB', 'nama' => 'Laboratorium Komputer']);
    $ruangan->pic()->attach($pic->id, ['utama' => true]);
    KodefikasiBarang::query()->create(['kode' => '3100203001', 'uraian' => 'Personal Computer', 'tingkat' => 5, 'kategori_lokal' => 'Elektronik']);
    $aset = Aset::factory()->diRuangan($ruangan)->create([
        'nama' => 'Komputer Desktop', 'merk_tipe' => 'Dell OptiPlex', 'kode_barang' => '3100203001', 'nup' => 42,
        'tahun_perolehan' => 2022, 'nilai_perolehan' => '98765432.10', 'nomor_dokumen_perolehan' => 'BAST-RAHASIA-77',
        'keterangan' => 'catatan internal sangat rahasia', 'kondisi' => KondisiAset::RusakRingan, 'spesifikasi' => 'spesifikasi internal',
    ]);
    $aset->riwayatKondisi()->create(['ke' => 'RR', 'sumber' => 'manual', 'catatan' => 'riwayat-rahasia-xyz']);

    $respons = $this->get('/a/'.$aset->id);

    $respons->assertOk()
        ->assertSee('Komputer Desktop')->assertSee('Dell OptiPlex')->assertSee('Elektronik')->assertSee('Laboratorium Komputer')
        ->assertSee('Rusak Ringan')->assertSee('2022')->assertSee('3100203001 / 42')
        ->assertSee('Laporkan kerusakan')->assertSee('/lapor-kerusakan/'.$aset->id)
        ->assertDontSee('98765432')->assertDontSee('98.765.432')->assertDontSee('BAST-RAHASIA')->assertDontSee('catatan internal')
        ->assertDontSee('Rahmat')->assertDontSee('riwayat-rahasia')->assertDontSee('spesifikasi internal')->assertDontSee($pic->email);
    expect($respons->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and($respons->getContent())->toContain('<meta name="robots" content="noindex, nofollow">');
});

it('lookup tidak menampilkan aset yang sudah dihapus atau id acak', function () {
    $aset = Aset::factory()->create(['status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK-1', 'tanggal_sk_penghapusan' => '2026-01-01']);

    $this->get('/a/'.$aset->id)->assertNotFound();
    $this->get('/a/'.fake()->uuid())->assertNotFound();
    $this->get('/a/bukan-uuid')->assertNotFound();
});

it('membatasi lookup 30 permintaan per menit per IP (31 → 429)', function () {
    $aset = Aset::factory()->create();

    foreach (range(1, 30) as $i) {
        $this->get('/a/'.$aset->id)->assertOk();
    }

    $this->get('/a/'.$aset->id)->assertStatus(429);
    $this->get('/l/APA-SAJA-1')->assertStatus(429); // pembatas dipakai bersama oleh /a dan /l
});

it('tidak ada endpoint publik yang mengembalikan daftar aset', function () {
    Aset::factory()->count(3)->create();

    foreach (['/a', '/a/', '/l', '/l/', '/aset', '/api/aset', '/api/v1/aset'] as $jalur) {
        expect($this->get($jalur)->status())->toBeIn([301, 302, 401, 404, 405]);
    }
});

it('resolusi menerima URL baru, UUID polos, dan mengabaikan aset dihapus', function () {
    $aset = Aset::factory()->create();
    $hapus = Aset::factory()->create(['status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK-1', 'tanggal_sk_penghapusan' => '2026-01-01']);
    $r = app(ResolusiLabel::class);

    expect($r->handle(url('/a/'.$aset->id))->id)->toBe($aset->id)
        ->and($r->handle('https://aset.fkip.unsil.ac.id/a/'.strtoupper($aset->id).'/')->id)->toBe($aset->id)
        ->and($r->handle($aset->id)->id)->toBe($aset->id)
        ->and($r->handle(url('/a/'.$hapus->id)))->toBeNull()
        ->and($r->handle('https://evil.test/x/'.$aset->id))->toBeNull()
        ->and($r->handle(''))->toBeNull();
});

it('halaman /pindai memerlukan login', function () {
    $this->get('/pindai')->assertRedirect('/login');
});

it('halaman /pindai menampilkan aset dari URL baru dan teks label lama', function () {
    $aset = asetDenganLabelLama(['MBL-935464-1'], ['nama' => 'Meja Rapat']);
    $this->actingAs(tap(User::factory()->create(), fn ($u) => $u->assignRole('pic-ruangan')));

    $this->get('/pindai')->assertOk()->assertSee('Pindai QR aset');

    Livewire::test(Pindai::class)
        ->set('teks', url('/a/'.$aset->id))->call('cari')
        ->assertSee('Meja Rapat')->assertSee('Laporkan kerusakan')
        ->call('ulang')->assertDontSee('Meja Rapat')
        ->set('teks', 'mbl-935464-1')->call('cari')
        ->assertSee('Meja Rapat');

    Livewire::test(Pindai::class)->set('teks', 'TIDAK-ADA')->call('cari')->assertSee('tidak ditemukan');
    Livewire::test(Pindai::class)->set('teks', '')->call('cari')->assertHasErrors('teks');
});

it('aksi di /pindai mengikuti peran: PIC ruangannya dapat ubah kondisi, selain itu tidak', function () {
    [$r1, $r2] = [Ruangan::query()->create(['kode' => 'R-1', 'nama' => 'Satu']), Ruangan::query()->create(['kode' => 'R-2', 'nama' => 'Dua'])];
    $pic = User::factory()->create();
    $pic->assignRole('pic-ruangan');
    $pic->ruanganDikelola()->attach($r1->id);
    $milik = Aset::factory()->diRuangan($r1)->create();
    $lain = Aset::factory()->diRuangan($r2)->create();
    $this->actingAs($pic);

    Livewire::test(Pindai::class)->set('teks', $milik->id)->call('cari')
        ->assertSee('Jadikan Rusak Berat')
        ->call('ubahKondisi', 'RB')
        ->assertSee('Kondisi diperbarui');
    expect($milik->fresh()->kondisi)->toBe(KondisiAset::RusakBerat)
        ->and($milik->riwayatKondisi()->first()->oleh)->toBe($pic->id);

    Livewire::test(Pindai::class)->set('teks', $lain->id)->call('cari')
        ->assertDontSee('Jadikan Rusak Berat')
        ->call('ubahKondisi', 'RB')->assertForbidden();
    expect($lain->fresh()->kondisi)->toBe(KondisiAset::Baik);
});

it('civitas hanya melihat data dasar di /pindai tanpa status internal dan tanpa tautan panel', function () {
    $aset = Aset::factory()->create(['nama' => 'Kursi Aula']);
    $this->actingAs(tap(User::factory()->create(), fn ($u) => $u->assignRole('civitas')));

    Livewire::test(Pindai::class)->set('teks', $aset->id)->call('cari')
        ->assertSee('Kursi Aula')->assertDontSee('Status')->assertDontSee('Buka di panel')->assertDontSee('Jadikan');
});
