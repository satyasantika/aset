<?php

use App\Actions\Dbr\BangkitkanDbr;
use App\Actions\Dbr\SahkanDbr;
use App\Actions\Dbr\SetujuiDbrOlehPic;
use App\Console\Commands\PangkasPeminjaman;
use App\Enums\StatusDbr;
use App\Enums\StatusPeminjaman;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use App\Notifications\PeminjamanTerlambat;
use App\Notifications\PengingatInventarisasi;
use App\Notifications\PengingatPengambilan;
use App\Support\Pengaturan;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Cache::flush();
    Notification::fake();
});

function pjUser(string $peran, array $ruangan = []): User
{
    $u = User::factory()->create(['aktif' => true]);
    $u->assignRole($peran);
    $u->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));

    return $u;
}

function pjAset(): array
{
    $r = Ruangan::query()->create(['kode' => 'J-'.fake()->unique()->numerify('###'), 'nama' => 'Ruang J', 'dapat_dipinjam' => true]);

    return [$r, Aset::factory()->diRuangan($r)->create(['dapat_dipinjam' => true, 'kode_barang' => '3100102001'])];
}

test('jadwal memuat semua perintah dengan withoutOverlapping dan onOneServer', function () {
    $acara = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'aset:'));

    expect($acara)->toHaveCount(7)
        ->and($acara->every(fn ($e) => $e->withoutOverlapping && $e->onOneServer))->toBeTrue();
});

test('pengingat terlambat: peminjam dan PIC, hanya yang lewat batas, sekali per hari', function () {
    [$r, $aset] = pjAset();
    $pic = pjUser('pic-ruangan', [$r]);
    $peminjam = pjUser('civitas');
    $mulai = now()->subDays(5)->toDateTimeString();
    $terlambat = Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->rentang($mulai, now()->subDay()->toDateTimeString())->untuk($aset)->create(['peminjam_user_id' => $peminjam->id]);
    [, $aset2] = pjAset();
    Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->rentang($mulai, now()->addDay()->toDateTimeString())->untuk($aset2)->create();

    $this->artisan('aset:pengingat-terlambat')->assertSuccessful();
    $this->artisan('aset:pengingat-terlambat')->assertSuccessful();

    Notification::assertSentTo($pic, PeminjamanTerlambat::class, 1);
    Notification::assertSentTo($peminjam, PeminjamanTerlambat::class, 1);
    Notification::assertSentTimes(PeminjamanTerlambat::class, 2);
    expect($terlambat->terlambat())->toBeTrue();
});

test('pengingat pengambilan: disetujui yang mulai besok', function () {
    [$r, $aset] = pjAset();
    $pic = pjUser('pic-ruangan', [$r]);
    Peminjaman::factory()->status(StatusPeminjaman::Disetujui)->rentang(now()->addDay()->setTime(9, 0)->toDateTimeString(), now()->addDays(2)->toDateTimeString())->untuk($aset)->create();
    [, $aset2] = pjAset();
    Peminjaman::factory()->status(StatusPeminjaman::Disetujui)->rentang(now()->addDays(3)->toDateTimeString(), now()->addDays(4)->toDateTimeString())->untuk($aset2)->create();

    $this->artisan('aset:pengingat-pengambilan')->assertSuccessful();

    Notification::assertSentTo($pic, PengingatPengambilan::class, 1);
    Notification::assertSentTimes(PengingatPengambilan::class, 1);
});

test('tandai DBR usang: hanya yang daftar asetnya sudah berbeda dari snapshot', function () {
    [$r, $aset] = pjAset();
    $admin = pjUser('admin-bmn');
    $pejabat = pjUser('pejabat-penatausahaan');
    $dbr = app(BangkitkanDbr::class)->handle($r, $admin);
    app(SetujuiDbrOlehPic::class)->handle($dbr, pjUser('pic-ruangan', [$r]));
    app(SahkanDbr::class)->handle($dbr->fresh(), $pejabat);

    $this->artisan('aset:tandai-dbr-usang')->assertSuccessful();
    expect($dbr->fresh()->status)->toBe(StatusDbr::Disahkan);

    // ubah aset tanpa observer (mis. impor/SQL langsung) → jaring pengaman harus menangkap
    DB::table('aset')->where('id', $aset->id)->update(['nama' => 'Nama Diubah Diam-diam']);
    $this->artisan('aset:tandai-dbr-usang')->assertSuccessful();

    expect($dbr->fresh()->status)->toBe(StatusDbr::PerluDiperbarui);
});

test('pengingat inventarisasi: terkirim ke admin dan pejabat bila belum ada inventarisasi disahkan', function () {
    $admin = pjUser('admin-bmn');
    $pejabat = pjUser('pejabat-penatausahaan');

    $this->artisan('aset:pengingat-inventarisasi')->assertSuccessful();

    Notification::assertSentTo($admin, PengingatInventarisasi::class);
    Notification::assertSentTo($pejabat, PengingatInventarisasi::class);
});

test('pangkas peminjaman: anonimkan data pribadi setelah retensi, tidak menyentuh yang masih baru atau aktif', function () {
    [, $aset] = pjAset();
    $tua = Peminjaman::factory()->status(StatusPeminjaman::Dikembalikan)->untuk($aset)->create(['nama_peminjam' => 'Budi Lama', 'kontak_peminjam' => '0812', 'unit_peminjam' => 'Prodi', 'keperluan' => 'Rahasia']);
    $baru = Peminjaman::factory()->status(StatusPeminjaman::Dikembalikan)->untuk($aset)->create(['nama_peminjam' => 'Siti Baru']);
    $aktif = Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->untuk($aset)->create(['nama_peminjam' => 'Ani Aktif']);
    DB::table('peminjaman')->whereIn('id', [$tua->id, $aktif->id])->update(['updated_at' => now()->subMonths(40)]);

    $this->artisan('aset:pangkas-peminjaman')->assertSuccessful();

    expect($tua->fresh()->nama_peminjam)->toBe(PangkasPeminjaman::PENANDA)
        ->and($tua->fresh()->kontak_peminjam)->toBeNull()
        ->and($tua->fresh()->keperluan)->toBe(PangkasPeminjaman::PENANDA)
        ->and($baru->fresh()->nama_peminjam)->toBe('Siti Baru')
        ->and($aktif->fresh()->nama_peminjam)->toBe('Ani Aktif');

    Pengaturan::simpan('retensi_peminjaman_bulan', 1);
    DB::table('peminjaman')->where('id', $baru->id)->update(['updated_at' => now()->subMonths(2)]);
    $this->artisan('aset:pangkas-peminjaman')->assertSuccessful();
    expect($baru->fresh()->nama_peminjam)->toBe(PangkasPeminjaman::PENANDA);
});

test('bersihkan tmp: hapus berkas lebih tua dari 24 jam saja', function () {
    Storage::fake('tmp');
    Storage::disk('tmp')->put('ekspor/lama.csv', 'x');
    Storage::disk('tmp')->put('ekspor/baru.csv', 'y');
    touch(Storage::disk('tmp')->path('ekspor/lama.csv'), now()->subHours(25)->getTimestamp());

    $this->artisan('aset:bersihkan-tmp')->assertSuccessful();

    Storage::disk('tmp')->assertMissing('ekspor/lama.csv');
    Storage::disk('tmp')->assertExists('ekspor/baru.csv');
});

test('periksa tautan mengantrekan satu job per tautan', function () {
    Queue::fake();
    [, $aset] = pjAset();
    $aset->tautanBerkas()->create(['jenis' => 'foto', 'label' => 'Foto', 'url' => 'https://drive.google.com/file/d/abc/view']);

    $this->artisan('aset:periksa-tautan')->assertSuccessful();

    Queue::assertPushed(PeriksaTautanBerkas::class, 1);
});
