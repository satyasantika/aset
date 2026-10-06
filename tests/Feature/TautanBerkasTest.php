<?php

use App\Contracts\PenyimpananBerkas;
use App\Enums\PenyediaBerkas;
use App\Enums\StatusCekTautan;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Ruangan;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Notifications\TautanBerkasMati;
use App\Rules\TautanBerkasValid;
use App\Services\PemeriksaTautan;
use App\Services\TautanEksternal;
use App\Support\TautanBerkasParser;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

const DRIVE = 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/view?usp=sharing';

function lolosTautan(string $url, bool $berkasSaja = true): bool
{
    return Validator::make(['u' => $url], ['u' => [new TautanBerkasValid($berkasSaja)]])->passes();
}

it('memvalidasi tautan berkas sesuai daftar putih §1a.2', function (string $url, bool $lolos) {
    expect(lolosTautan($url))->toBe($lolos);
})->with([
    'drive file' => [DRIVE, true],
    'docs' => ['https://docs.google.com/document/d/1AbCdEfGhIjKlMnOpQrStUv/edit', true],
    'subdomain unsil' => ['https://repo.fkip.unsil.ac.id/berkas/sk.pdf', true],
    'onedrive' => ['https://onedrive.live.com/?id=ABC123', true],
    'sharepoint' => ['https://unsil-my.sharepoint.com/x/s/abc', true],
    'http biasa' => ['http://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view', false],
    'domain lain' => ['https://example.com/berkas.pdf', false],
    'pemendek bit.ly' => ['https://bit.ly/3abc', false],
    'pemendek s.id' => ['https://s.id/abc', false],
    'folder drive' => ['https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUv', false],
    'domain tiruan' => ['https://drive.google.com.evil.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view', false],
    'unsil tiruan' => ['https://unsil.ac.id.evil.com/a', false],
    'bukan url' => ['sk penghapusan', false],
]);

it('mengizinkan tautan folder bila bukan mode satu berkas', function () {
    expect(lolosTautan('https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUv', false))->toBeTrue();
});

it('mengekstrak id berkas Drive dan penyedia dari berbagai pola URL', function () {
    expect(TautanBerkasParser::idDrive(DRIVE))->toBe('1AbCdEfGhIjKlMnOpQrStUvWxYz')
        ->and(TautanBerkasParser::idDrive('https://drive.google.com/open?id=1AbCdEfGhIjKlMnOpQrStUv'))->toBe('1AbCdEfGhIjKlMnOpQrStUv')
        ->and(TautanBerkasParser::idDrive('https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQrStUv/edit'))->toBe('1AbCdEfGhIjKlMnOpQrStUv')
        ->and(TautanBerkasParser::idDrive('https://repo.unsil.ac.id/x'))->toBeNull()
        ->and(TautanBerkasParser::penyedia(DRIVE))->toBe(PenyediaBerkas::GoogleDrive)
        ->and(TautanBerkasParser::penyedia('https://x.unsil.ac.id/a'))->toBe(PenyediaBerkas::Unsil)
        ->and(TautanBerkasParser::urlFoto(DRIVE))->toBe('https://lh3.googleusercontent.com/d/1AbCdEfGhIjKlMnOpQrStUvWxYz')
        ->and(TautanBerkasParser::urlPratinjau(DRIVE))->toBe('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/preview');
});

it('mengikat PenyimpananBerkas ke TautanEksternal sesuai BERKAS_MODE', function () {
    expect(app(PenyimpananBerkas::class))->toBeInstanceOf(TautanEksternal::class)
        ->and(config('berkas.mode'))->toBe('tautan');

    config(['berkas.mode' => 'lain']);
    expect(fn () => app(PenyimpananBerkas::class))->toThrow(RuntimeException::class);
});

it('menyimpan tautan polimorfik, mengisi metadata, dan mengantre pemeriksaan di antrean tautan', function () {
    Bus::fake();
    $pengguna = User::factory()->create();
    $pemilik = Ruangan::query()->create(['kode' => 'R-A', 'nama' => 'A']);

    $tautan = app(PenyimpananBerkas::class)->tambah($pemilik, 'foto', 'Foto gedung', DRIVE, $pengguna);

    expect($tautan->pemilik_type)->toBe($pemilik->getMorphClass())
        ->and($tautan->pemilik_id)->toBe($pemilik->id)
        ->and($tautan->penyedia)->toBe(PenyediaBerkas::GoogleDrive)
        ->and($tautan->drive_file_id)->toBe('1AbCdEfGhIjKlMnOpQrStUvWxYz')
        ->and($tautan->status_cek)->toBe(StatusCekTautan::Belum)
        ->and($tautan->ditambahkan_oleh)->toBe($pengguna->id)
        ->and(app(PenyimpananBerkas::class)->daftar($pemilik, 'foto'))->toHaveCount(1)
        ->and(app(PenyimpananBerkas::class)->daftar($pemilik, 'sk'))->toHaveCount(0);

    Bus::assertDispatched(PeriksaTautanBerkas::class, fn ($job) => $job->queue === 'tautan' && $job->tautanId === $tautan->id);
});

it('menolak tautan tidak valid saat disimpan lewat penyimpanan', function () {
    $pemilik = Ruangan::query()->create(['kode' => 'R-A', 'nama' => 'A']);

    expect(fn () => app(PenyimpananBerkas::class)->tambah($pemilik, 'sk', 'SK', 'https://bit.ly/abc'))
        ->toThrow(ValidationException::class);
    expect(TautanBerkas::count())->toBe(0);
});

it('mereset status cek saat URL diganti dan mencatat perubahan URL di jejak audit', function () {
    Bus::fake();
    $pemilik = Ruangan::query()->create(['kode' => 'R-A', 'nama' => 'A']);
    $pengguna = User::factory()->create();
    $this->actingAs($pengguna);
    $tautan = app(PenyimpananBerkas::class)->tambah($pemilik, 'sk', 'SK', DRIVE);
    $tautan->forceFill(['status_cek' => StatusCekTautan::DapatDiakses, 'dicek_pada' => now()])->saveQuietly();

    $baru = 'https://drive.google.com/file/d/1ZzYyXxWwVvUuTtSsRrQqPp/view';
    app(PenyimpananBerkas::class)->perbarui($tautan, $baru);

    $log = DB::table('activity_log')->where('subject_id', $tautan->id)->where('description', 'updated')->latest('created_at')->first();
    $properti = json_decode($log->properties, true);

    expect($tautan->refresh()->status_cek)->toBe(StatusCekTautan::Belum)
        ->and($tautan->drive_file_id)->toBe('1ZzYyXxWwVvUuTtSsRrQqPp')
        ->and($properti['old']['url'])->toBe(DRIVE)
        ->and($properti['attributes']['url'])->toBe($baru)
        ->and($log->causer_id)->toBe($pengguna->id);
});

function pemeriksa(?array $ip = null): PemeriksaTautan
{
    return new PemeriksaTautan(fn (string $host) => $ip ?? ['142.250.190.14']);
}

it('menandai tautan dapat diakses bila HEAD mengembalikan 200', function () {
    Http::fake(['drive.google.com/*' => Http::response('', 200)]);

    expect(pemeriksa()->periksa(DRIVE))->toBe(StatusCekTautan::DapatDiakses);
});

it('memakai GET ber-stream bila HEAD tidak didukung', function () {
    Http::fake([
        'drive.google.com/*' => Http::sequence()->push('', 405)->push('isi', 200),
    ]);

    expect(pemeriksa()->periksa(DRIVE))->toBe(StatusCekTautan::DapatDiakses);
    Http::assertSentCount(2);
});

it('menandai tidak dapat diakses untuk 404, 403, dan pengalihan ke halaman login', function () {
    Http::fake(['drive.google.com/*' => Http::response('', 404)]);
    expect(pemeriksa()->periksa(DRIVE))->toBe(StatusCekTautan::TidakDapatDiakses);

    Http::fake(['drive.google.com/*' => Http::response('', 403)]);
    expect(pemeriksa()->periksa(DRIVE))->toBe(StatusCekTautan::TidakDapatDiakses);

    Http::fake(['drive.google.com/*' => Http::response('', 302, ['Location' => 'https://accounts.google.com/ServiceLogin?x=1'])]);
    expect(pemeriksa()->periksa(DRIVE))->toBe(StatusCekTautan::TidakDapatDiakses);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'accounts.google.com'));
});

it('tidak menyimpulkan apa pun saat 5xx atau jaringan gagal', function () {
    Http::fake(['drive.google.com/*' => Http::response('', 503)]);
    expect(pemeriksa()->periksa(DRIVE))->toBeNull();

    Http::fake(['drive.google.com/*' => fn () => throw new ConnectionException('timeout')]);
    expect(pemeriksa()->periksa(DRIVE))->toBeNull();
});

it('menolak SSRF: host di luar daftar putih, IP privat/loopback, dan redirect ke dalam jaringan', function () {
    Http::fake();

    expect(pemeriksa()->periksa('https://internal.example.com/x'))->toBe(StatusCekTautan::TidakDapatDiakses)
        ->and(pemeriksa()->periksa('http://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view'))->toBe(StatusCekTautan::TidakDapatDiakses)
        ->and(pemeriksa(['127.0.0.1'])->periksa(DRIVE))->toBe(StatusCekTautan::TidakDapatDiakses)
        ->and(pemeriksa(['10.0.0.5'])->periksa(DRIVE))->toBe(StatusCekTautan::TidakDapatDiakses)
        ->and(pemeriksa(['169.254.169.254'])->periksa(DRIVE))->toBe(StatusCekTautan::TidakDapatDiakses)
        ->and(pemeriksa(['142.250.190.14', '192.168.1.1'])->periksa(DRIVE))->toBe(StatusCekTautan::TidakDapatDiakses)
        ->and(pemeriksa([])->periksa(DRIVE))->toBe(StatusCekTautan::TidakDapatDiakses);
    Http::assertNothingSent();
});

it('menolak SSRF lewat redirect ke alamat dalam jaringan', function () {
    Http::fake(['drive.google.com/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data'])]);
    expect(pemeriksa()->periksa(DRIVE))->toBe(StatusCekTautan::TidakDapatDiakses);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '169.254.169.254'));
});

it('membatasi jumlah redirect', function () {
    Http::fake(['drive.google.com/*' => Http::response('', 302, ['Location' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view'])]);

    expect(pemeriksa()->periksa(DRIVE))->toBe(StatusCekTautan::TidakDapatDiakses);
    Http::assertSentCount(4); // 1 awal + 3 redirect
});

it('job memperbarui status dan memberi tahu penambah saat tautan mati (sekali)', function () {
    Notification::fake();
    $penambah = User::factory()->create();
    $pemilik = Ruangan::query()->create(['kode' => 'R-A', 'nama' => 'A']);
    $tautan = $pemilik->tautanBerkas()->create(['jenis' => 'sk', 'label' => 'SK', 'url' => DRIVE, 'ditambahkan_oleh' => $penambah->id]);
    app()->instance(PemeriksaTautan::class, pemeriksa());
    Http::fake(['drive.google.com/*' => Http::response('', 404)]);

    (new PeriksaTautanBerkas($tautan->id))->handle(app(PemeriksaTautan::class));
    (new PeriksaTautanBerkas($tautan->id))->handle(app(PemeriksaTautan::class));

    expect($tautan->refresh()->status_cek)->toBe(StatusCekTautan::TidakDapatDiakses)
        ->and($tautan->dicek_pada)->not->toBeNull();
    Notification::assertSentToTimes($penambah, TautanBerkasMati::class, 1);
});

it('job mempertahankan status lama bila hasil tidak dapat disimpulkan', function () {
    $pemilik = Ruangan::query()->create(['kode' => 'R-A', 'nama' => 'A']);
    $tautan = $pemilik->tautanBerkas()->create(['jenis' => 'sk', 'label' => 'SK', 'url' => DRIVE]);
    $tautan->forceFill(['status_cek' => StatusCekTautan::DapatDiakses])->saveQuietly();
    Http::fake(['drive.google.com/*' => Http::response('', 503)]);

    (new PeriksaTautanBerkas($tautan->id))->handle(pemeriksa());

    expect($tautan->refresh()->status_cek)->toBe(StatusCekTautan::DapatDiakses);
});

it('merender komponen x-tautan-berkas dengan aman', function () {
    $pemilik = Ruangan::query()->create(['kode' => 'R-A', 'nama' => 'A']);
    $foto = $pemilik->tautanBerkas()->create(['jenis' => 'foto', 'label' => 'Foto <b>depan</b>', 'url' => DRIVE]);
    $pdf = $pemilik->tautanBerkas()->create(['jenis' => 'sk', 'label' => 'SK', 'url' => DRIVE, 'status_cek' => StatusCekTautan::TidakDapatDiakses]);

    $htmlFoto = Blade::render('<x-tautan-berkas :tautan="$t" />', ['t' => $foto]);
    $htmlPdf = Blade::render('<x-tautan-berkas :tautan="$t" :pratinjau="true" />', ['t' => $pdf]);

    expect($htmlFoto)->toContain('https://lh3.googleusercontent.com/d/1AbCdEfGhIjKlMnOpQrStUvWxYz')
        ->toContain('Foto tidak dapat dimuat')
        ->toContain('target="_blank"')->toContain('rel="noopener noreferrer"')
        ->not->toContain('<b>depan</b>')
        ->and($htmlPdf)->toContain('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/preview')
        ->toContain('tautan tidak dapat diakses');
});

arch('tidak ada FileUpload Filament atau input file di kode aplikasi (kebijakan tautan)')
    ->expect('App')
    ->not->toUse(FileUpload::class);

it('tidak memuat input type=file atau FileUpload di app dan resources', function () {
    $pelanggar = [];
    foreach ([app_path(), resource_path()] as $akar) {
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar, FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $berkas) {
            if (! in_array($berkas->getExtension(), ['php', 'blade.php'], true) && ! str_ends_with($berkas->getFilename(), '.blade.php')) {
                continue;
            }
            $isi = file_get_contents($berkas->getPathname());
            if (preg_match('/FileUpload|SpatieMediaLibrary|type=["\']file["\']/i', $isi)) {
                $pelanggar[] = str_replace(base_path().'/', '', $berkas->getPathname());
            }
        }
    }

    expect($pelanggar)->toBe([]);
});
