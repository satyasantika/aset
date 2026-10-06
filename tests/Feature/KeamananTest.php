<?php

use App\Actions\Mutasi\AjukanMutasi;
use App\Actions\Mutasi\SetujuiMutasi;
use App\Actions\Peminjaman\AjukanPeminjaman;
use App\Actions\Peminjaman\SetujuiPeminjaman;
use App\Enums\StatusDbr;
use App\Enums\StatusPeminjaman;
use App\Filament\Pages\Auth\Masuk;
use App\Http\Middleware\HeaderKeamanan;
use App\Livewire\Pindai;
use App\Livewire\PinjamanSaya;
use App\Models\Aset;
use App\Models\DbrVersi;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    RateLimiter::clear('lookup');
    RateLimiter::clear('lapor');
});

function kmUser(string $peran, array $ruangan = []): User
{
    $u = User::factory()->create(['aktif' => true]);
    $u->assignRole($peran);
    $u->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));

    return $u;
}

function kmRuangan(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => 'Ruang '.$kode, 'dapat_dipinjam' => true]);
}

function kmAset(Ruangan $r, array $atribut = []): Aset
{
    return Aset::factory()->diRuangan($r)->create(['dapat_dipinjam' => true, 'kode_barang' => '3100102001', ...$atribut]);
}

// ── IDOR (UUID) ───────────────────────────────────────────────────────────────

it('IDOR: PIC ruangan lain tidak dapat membuka PDF DBR ruangan ini; id acak/non-UUID → 404', function () {
    $r = kmRuangan('K-1');
    $dbr = DbrVersi::query()->create(['ruangan_id' => $r->id, 'versi' => 1, 'status' => StatusDbr::Draf, 'snapshot' => ['aset' => []]]);
    $picLain = kmUser('pic-ruangan', [kmRuangan('K-2')]);

    $this->actingAs($picLain)->get('/cetak/dbr/'.$dbr->id)->assertForbidden();
    $this->actingAs($picLain)->get('/cetak/dbr/01a11172-0000-7000-8000-000000000000')->assertNotFound();
    $this->actingAs($picLain)->get('/cetak/dbr/1')->assertNotFound();
});

it('IDOR: halaman publik menolak id berurutan dan berkas hanya dilayani lewat UUID', function () {
    $this->get('/a/1')->assertNotFound();
    $this->get('/lapor-kerusakan/1')->assertNotFound();
});

it('IDOR: civitas hanya melihat pinjamannya sendiri; PIC hanya menulis aset ruangannya', function () {
    $r = kmRuangan('K-3');
    $a = kmAset($r);
    $saya = kmUser('civitas');
    $orangLain = kmUser('civitas');
    $milikSaya = Peminjaman::factory()->status(StatusPeminjaman::Diajukan)->untuk($a)->create(['peminjam_user_id' => $saya->id]);
    $milikLain = Peminjaman::factory()->status(StatusPeminjaman::Diajukan)->untuk($a)->create(['peminjam_user_id' => $orangLain->id]);

    $this->actingAs($saya);
    Livewire::test(PinjamanSaya::class)->assertSee($milikSaya->nomor)->assertDontSee($milikLain->nomor);

    $pic = kmUser('pic-ruangan', [$r]);
    $asetLain = kmAset(kmRuangan('K-4'), ['nup' => 77]);

    // Aset dapat dilihat PIC (matriks: S), tetapi aksi tulis hanya untuk ruangannya sendiri.
    expect(Gate::forUser($pic)->allows('ubahKondisi', $a))->toBeTrue()
        ->and(Gate::forUser($pic)->allows('ubahKondisi', $asetLain))->toBeFalse()
        ->and(Gate::forUser($pic)->allows('cetakLabel', $asetLain))->toBeFalse();
});

// ── Rate limit ────────────────────────────────────────────────────────────────

it('rate limit: lookup publik 30/menit/IP → permintaan ke-31 = 429', function () {
    $aset = kmAset(kmRuangan('K-5'));

    for ($i = 0; $i < 30; $i++) {
        $this->get('/a/'.$aset->id)->assertOk();
    }

    $this->get('/a/'.$aset->id)->assertStatus(429);
});

it('rate limit: login panel dibatasi 5 percobaan (diuji rinci di LoginAmanTest)', function () {
    expect(Masuk::BATAS_PERCOBAAN)->toBe(5);
});

// ── XSS ───────────────────────────────────────────────────────────────────────

it('XSS: nama aset, kode pindai, dan keperluan peminjaman di-escape di semua tampilan', function () {
    $r = kmRuangan('K-6');
    $payload = '<script>alert("xss")</script>';
    $aset = kmAset($r, ['nama' => $payload, 'merk_tipe' => $payload]);

    $res = $this->get('/a/'.$aset->id)->assertOk();
    expect($res->getContent())->not->toContain($payload)->toContain('&lt;script&gt;');

    $this->get('/lapor-kerusakan/'.$aset->id)->assertOk()->assertDontSee($payload, false);

    $this->actingAs(kmUser('civitas'));
    Livewire::test(Pindai::class)->set('teks', $payload)->call('cari')->assertDontSee($payload, false)->assertSee('tidak ditemukan');

    $saya = kmUser('civitas');
    Peminjaman::factory()->status(StatusPeminjaman::Diajukan)->untuk($aset)->create(['peminjam_user_id' => $saya->id, 'keperluan' => $payload]);
    $this->actingAs($saya);
    Livewire::test(PinjamanSaya::class)->assertDontSee($payload, false);
});

it('XSS: tidak ada keluaran Blade tanpa escape ({!! !!}) di view aplikasi', function () {
    $ditemukan = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($f) => str_contains($f->getContents(), '{!!'))
        ->map(fn ($f) => $f->getRelativePathname())->values()->all();

    expect($ditemukan)->toBe([]);
});

// ── Tanpa unggah berkas ───────────────────────────────────────────────────────

it('tanpa unggah: tidak ada komponen/penangan unggah berkas di kode aplikasi (kecuali impor)', function () {
    $pola = ['FileUpload', '->hasFile(', '->file(', 'UploadedFile', 'type="file"'];
    $pelanggar = [];

    foreach (['app', 'resources/views'] as $dir) {
        foreach (File::allFiles(base_path($dir)) as $berkas) {
            $isi = $berkas->getContents();

            foreach ($pola as $p) {
                if (str_contains($isi, $p)) {
                    $pelanggar[] = $berkas->getRelativePathname().' → '.$p;
                }
            }
        }
    }

    expect($pelanggar)->toBe([]);
});

it('tanpa unggah: berkas yang dikirim ke lapor kerusakan diabaikan dan tidak disimpan', function () {
    Storage::fake('local');
    Storage::fake('tmp');
    $aset = kmAset(kmRuangan('K-7'));

    $this->post('/lapor-kerusakan/'.$aset->id, [
        'deskripsi' => 'Layar retak', 'foto' => UploadedFile::fake()->image('x.jpg'), 'situs_web' => '',
    ]);

    expect(Storage::disk('local')->allFiles())->toBe([])->and(Storage::disk('tmp')->allFiles())->toBe([]);
});

// ── Konkurensi ────────────────────────────────────────────────────────────────

it('konkurensi: dua pengajuan tumpang tindih — yang kedua gagal saat persetujuan (BR-08)', function () {
    config(['aset.lock_tunggu_detik' => 1]);
    $r = kmRuangan('K-8');
    $pic = kmUser('pic-ruangan', [$r]);
    $aset = kmAset($r);
    $a = app(AjukanPeminjaman::class)->handle([$aset->id], 'A', now()->addDay(), now()->addDays(2), kmUser('civitas'));
    $b = app(AjukanPeminjaman::class)->handle([$aset->id], 'B', now()->addDay()->addHour(), now()->addDays(3), kmUser('civitas'));

    app(SetujuiPeminjaman::class)->handle($a, $pic);

    expect(fn () => app(SetujuiPeminjaman::class)->handle($b, $pic))->toThrow(ValidationException::class)
        ->and($b->fresh()->status)->toBe(StatusPeminjaman::Diajukan);
});

it('konkurensi: persetujuan menunggu lock aset yang sedang dipegang proses lain lalu gagal rapi', function () {
    config(['aset.lock_tunggu_detik' => 1]);
    $r = kmRuangan('K-9');
    $pic = kmUser('pic-ruangan', [$r]);
    $aset = kmAset($r);
    $p = app(AjukanPeminjaman::class)->handle([$aset->id], 'A', now()->addDay(), now()->addDays(2), kmUser('civitas'));

    $lock = Cache::lock("aset:pinjam:{$aset->id}", 30);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => app(SetujuiPeminjaman::class)->handle($p, $pic))->toThrow(ValidationException::class)
            ->and($p->fresh()->status)->toBe(StatusPeminjaman::Diajukan);
    } finally {
        $lock->release();
    }
});

it('konkurensi: aset yang sudah ada di pengajuan mutasi menunggu tidak dapat diajukan lagi; setelah pindah, ajukan dari ruangan lama ditolak', function () {
    $asal = kmRuangan('K-10');
    $tujuan1 = kmRuangan('K-11');
    $tujuan2 = kmRuangan('K-12');
    $pic = kmUser('pic-ruangan', [$asal]);
    $admin = kmUser('admin-bmn');
    $aset = kmAset($asal);

    $m1 = app(AjukanMutasi::class)->handle($asal, $tujuan1, [$aset->id], 'pindah 1', $pic);

    expect(fn () => app(AjukanMutasi::class)->handle($asal, $tujuan2, [$aset->id], 'pindah 2', $pic))->toThrow(ValidationException::class);

    app(SetujuiMutasi::class)->handle($m1, $admin);

    expect(fn () => app(AjukanMutasi::class)->handle($asal, $tujuan2, [$aset->id], 'pindah 3', $pic))->toThrow(ValidationException::class)
        ->and($aset->fresh()->ruangan_id)->toBe($tujuan1->id);
});

// ── Header keamanan ───────────────────────────────────────────────────────────

it('header keamanan terpasang pada halaman web dan API; HSTS hanya di produksi', function () {
    $res = $this->get('/login');
    $res->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy')->assertHeader('Content-Security-Policy', HeaderKeamanan::CSP);
    expect($res->headers->get('Permissions-Policy'))->toContain('camera=(self)')
        ->and($res->headers->has('Strict-Transport-Security'))->toBeFalse()
        ->and(HeaderKeamanan::CSP)->toContain("object-src 'none'")->toContain("frame-ancestors 'self'");

    $this->getJson('/api/health')->assertHeader('X-Content-Type-Options', 'nosniff');

    app()->detectEnvironment(fn () => 'production');
    $this->get('/login')->assertHeader('Strict-Transport-Security');
});

it('tidak ada pemanggilan layanan QR pihak ketiga (QR dibangkitkan di server)', function () {
    $dilarang = ['api.qrserver.com', 'chart.googleapis.com', 'qrcode.tec-it.com', 'quickchart.io'];
    $temuan = [];

    foreach (['app', 'resources', 'config', 'routes'] as $dir) {
        foreach (File::allFiles(base_path($dir)) as $berkas) {
            foreach ($dilarang as $host) {
                if (str_contains($berkas->getContents(), $host)) {
                    $temuan[] = $berkas->getRelativePathname().' → '.$host;
                }
            }
        }
    }

    expect($temuan)->toBe([]);
});
