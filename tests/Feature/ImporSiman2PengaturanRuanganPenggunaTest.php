<?php

use App\Actions\Migrasi\Siman2\Nilai;
use App\Models\ImporSiman2Log;
use App\Models\KategoriRuangan;
use App\Models\Ruangan;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\Pengaturan;
use App\Support\UrlFotoLama;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Auth\Notifications\ResetPassword as ResetBawaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;
use Spatie\Activitylog\ActivityLogStatus;
use Tests\Support\Siman2Fixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Notification::fake();
});

afterEach(fn () => Siman2Fixture::bersihkan());

function petaStandar(): string
{
    return Siman2Fixture::pemetaan(['admin' => 'admin@unsil.ac.id', 'petugas1' => 'petugas1@unsil.ac.id', 'pimpinan' => 'pimpinan@unsil.ac.id']);
}

function jalankanImpor(string $berkas, string $pemetaan, array $opsi = []): PendingCommand
{
    return test()->artisan('siman2:impor', ['berkas' => $berkas, '--pemetaan' => $pemetaan, ...$opsi]);
}

it('dry-run melaporkan tanpa menulis data apa pun, tanpa surel dan tanpa menghapus berkas', function () {
    $berkas = Siman2Fixture::buat(Siman2Fixture::dasar());

    jalankanImpor($berkas, petaStandar(), ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('DRY-RUN')
        ->expectsOutputToContain('Ejaan "KEMENTRIAN"')
        ->expectsOutputToContain('Ruangan "Ruang Hantu" tidak ditemukan');

    expect(User::count())->toBe(0)->and(Ruangan::count())->toBe(0)->and(KategoriRuangan::count())->toBe(0)
        ->and(ImporSiman2Log::count())->toBe(0)->and(TautanBerkas::count())->toBe(0)
        ->and(DB::table('pengaturan')->count())->toBe(0)->and(DB::table('ruangan_pic')->count())->toBe(0);
    Notification::assertNothingSent();
    expect(file_exists($berkas))->toBeTrue();
});

it('dry-run mendeteksi galat basis data (kode ruangan ganda) tanpa menulis', function () {
    $dasar = Siman2Fixture::dasar();
    $dasar['ruangan'][2]['idRuangan'] = 'R-001'; // bentrok dengan Aula Utama
    $berkas = Siman2Fixture::buat($dasar);

    jalankanImpor($berkas, petaStandar(), ['--dry-run' => true])->assertFailed();

    expect(Ruangan::count())->toBe(0);
});

it('mengimpor pengaturan, kategori, ruangan, pengguna, dan PIC (F7.1)', function () {
    $berkas = Siman2Fixture::buat(Siman2Fixture::dasar());

    jalankanImpor($berkas, petaStandar())->assertSuccessful()->expectsOutputToContain('Impor selesai');

    // pengaturan
    expect(Pengaturan::ambil('instansi_baris2'))->toBe('UNIVERSITAS SILIWANGI')
        ->and(Pengaturan::ambil('nama_unit'))->toBe('FAKULTAS KEGURUAN DAN ILMU PENDIDIKAN')
        ->and(Pengaturan::ambil('penandatangan_nama'))->toBe('Arip Moh. Bahtiar')
        ->and(Pengaturan::ambil('penandatangan_nip'))->toBe('197303042008011004')
        ->and(Pengaturan::ambil('penanggung_jawab_nama'))->toBe('Redi Hermanto')
        ->and(Pengaturan::ambil('kota_surat'))->toBe('Tasikmalaya');

    // kategori ruangan + flag DKPS
    expect(KategoriRuangan::query()->where('nama', 'Laboratorium')->first())->adalah_laboratorium->toBeTrue()->adalah_ruang_kelas->toBeFalse()
        ->and(KategoriRuangan::query()->where('nama', 'Ruangan Kelas')->first())->adalah_ruang_kelas->toBeTrue();

    // ruangan: kode, kategori, kapasitas, kode otomatis bila kosong, foto Drive saja
    $aula = Ruangan::query()->where('kode', 'R-001')->first();
    expect($aula->nama)->toBe('Aula Utama')->and($aula->kapasitas)->toBe(500)->and($aula->kategori->nama)->toBe('Auditorium')
        ->and(Ruangan::query()->where('nama', 'Lab 2')->value('kode'))->toBe('R-003');
    $foto = $aula->tautanBerkas()->first();
    expect($foto->url)->toBe('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view')->and($foto->jenis)->toBe('foto')
        ->and(TautanBerkas::count())->toBe(1); // foto icons8 tidak dimigrasikan

    // pengguna: peran, surel dari pemetaan, tanpa kata sandi lama
    $admin = User::query()->where('email', 'admin@unsil.ac.id')->first();
    $petugas = User::query()->where('email', 'petugas1@unsil.ac.id')->first();
    expect($admin->hasRole('admin-bmn'))->toBeTrue()->and($admin->name)->toBe('Administrator')->and($admin->no_hp)->toBe('0811-2233-4455')
        ->and($petugas->hasRole('pic-ruangan'))->toBeTrue()
        ->and(User::query()->where('email', 'pimpinan@unsil.ac.id')->first()->hasRole('pimpinan'))->toBeTrue()
        ->and($petugas->aktif)->toBeTrue()
        ->and(Hash::check('123', $petugas->password))->toBeFalse()->and(Hash::check('admin123', $admin->password))->toBeFalse();

    // PIC: Lab 1 (utama via userIdPIC), Lab 2 (dari users.ruangan), Aula (utama via userIdPIC)
    expect($petugas->ruanganDikelola->pluck('nama')->sort()->values()->all())->toBe(['Aula Utama', 'Lab 1', 'Lab 2']);
    $utama = DB::table('ruangan_pic')->where('user_id', $petugas->id)->where('utama', true)->count();
    expect($utama)->toBe(3);   // PIC tunggal pada tiap ruangan dijadikan utama

    // surel atur kata sandi untuk akun baru
    Notification::assertSentToTimes($admin, ResetBawaan::class, 1);
    Notification::assertSentTo($petugas, ResetBawaan::class);

    // jejak per baris
    expect(ImporSiman2Log::query()->where('sheet', 'users')->where('status', 'ok')->count())->toBe(3)
        ->and(ImporSiman2Log::query()->where('sheet', 'ruangan')->count())->toBe(3);
});

it('tidak menulis audit log selama impor dan mengaktifkannya kembali sesudahnya', function () {
    $berkas = Siman2Fixture::buat(Siman2Fixture::dasar());
    $sebelum = DB::table('activity_log')->count();

    jalankanImpor($berkas, petaStandar())->assertSuccessful();

    expect(DB::table('activity_log')->count())->toBe($sebelum)
        ->and(app(ActivityLogStatus::class)->disabled())->toBeFalse();

    Ruangan::query()->create(['kode' => 'R-X', 'nama' => 'Uji audit']);
    expect(DB::table('activity_log')->count())->toBeGreaterThan($sebelum);
});

it('bersifat idempoten: impor ulang tidak menggandakan data dan tidak mengirim surel lagi', function () {
    $pemetaan = petaStandar();
    jalankanImpor(Siman2Fixture::buat(Siman2Fixture::dasar()), $pemetaan)->assertSuccessful();
    Notification::fake(); // reset hitungan

    $ruanganAwal = Ruangan::count();
    jalankanImpor(Siman2Fixture::buat(Siman2Fixture::dasar()), $pemetaan)->assertSuccessful();

    expect(User::count())->toBe(3)->and(Ruangan::count())->toBe($ruanganAwal)->and(KategoriRuangan::count())->toBe(3)
        ->and(TautanBerkas::count())->toBe(1)->and(DB::table('ruangan_pic')->count())->toBe(3)
        ->and(ImporSiman2Log::count())->toBe(ImporSiman2Log::query()->distinct()->count(DB::raw('sheet || id_lama')));
    Notification::assertNothingSent();
});

it('impor ulang memperbarui nilai yang berubah di sumber', function () {
    $pemetaan = petaStandar();
    jalankanImpor(Siman2Fixture::buat(Siman2Fixture::dasar()), $pemetaan)->assertSuccessful();

    $dasar = Siman2Fixture::dasar();
    $dasar['ruangan'][0]['namaRuangan'] = 'Aula Utama Baru';
    $dasar['ruangan'][0]['kapasitas'] = '600';
    $dasar['users'][1]['nama'] = 'Petugas Baru';
    jalankanImpor(Siman2Fixture::buat($dasar), $pemetaan)->assertSuccessful();

    expect(Ruangan::query()->where('kode', 'R-001')->first())->nama->toBe('Aula Utama Baru')->kapasitas->toBe(600)
        ->and(User::query()->where('email', 'petugas1@unsil.ac.id')->value('name'))->toBe('Petugas Baru')
        ->and(Ruangan::count())->toBe(3);
});

it('melaporkan galat untuk username tanpa pemetaan, role asing, dan surel non-unsil, tetapi mengimpor baris lain', function () {
    $dasar = Siman2Fixture::dasar();
    $dasar['users'][] = ['id' => 4, 'username' => 'tanpapeta', 'nama' => 'Tanpa Peta', 'role' => 'Admin', 'ruangan' => '', 'hp' => ''];
    $dasar['users'][] = ['id' => 5, 'username' => 'asing', 'nama' => 'Asing', 'role' => 'Dewa', 'ruangan' => '', 'hp' => ''];
    $dasar['users'][] = ['id' => 6, 'username' => 'gmail', 'nama' => 'Gmail', 'role' => 'Admin', 'ruangan' => '', 'hp' => ''];
    $pemetaan = Siman2Fixture::pemetaan(['admin' => 'admin@unsil.ac.id', 'petugas1' => 'petugas1@unsil.ac.id', 'pimpinan' => 'pimpinan@unsil.ac.id', 'asing' => 'asing@unsil.ac.id', 'gmail' => 'x@gmail.com']);

    jalankanImpor(Siman2Fixture::buat($dasar), $pemetaan)
        ->assertFailed()
        ->expectsOutputToContain('belum dipetakan')->expectsOutputToContain('tidak dikenal')->expectsOutputToContain('tidak valid');

    expect(User::count())->toBe(3)
        ->and(ImporSiman2Log::query()->where('sheet', 'users')->where('status', 'galat')->count())->toBe(3);
});

it('menautkan ke akun yang sudah ada dengan surel yang sama, bukan menggandakan', function () {
    $ada = User::factory()->create(['email' => 'petugas1@unsil.ac.id', 'name' => 'Nama Lama']);

    jalankanImpor(Siman2Fixture::buat(Siman2Fixture::dasar()), petaStandar())->assertSuccessful();

    expect(User::query()->where('email', 'petugas1@unsil.ac.id')->count())->toBe(1)
        ->and($ada->fresh())->name->toBe('Petugas Lab 1');
    $ada->refresh();
    expect($ada->hasRole('pic-ruangan'))->toBeTrue();
    Notification::assertNotSentTo($ada, ResetBawaan::class);
});

it('menghapus berkas ekspor di storage/app/tmp setelah impor sukses, mempertahankannya bila ada galat', function () {
    $bersih = Siman2Fixture::buat(Siman2Fixture::dasar());
    jalankanImpor($bersih, petaStandar())->assertSuccessful();
    expect(file_exists($bersih))->toBeFalse();

    $dasar = Siman2Fixture::dasar();
    $dasar['users'][] = ['id' => 9, 'username' => 'tanpapeta', 'nama' => 'X', 'role' => 'Admin', 'ruangan' => '', 'hp' => ''];
    $bergalat = Siman2Fixture::buat($dasar);
    jalankanImpor($bergalat, petaStandar())->assertFailed();
    expect(file_exists($bergalat))->toBeTrue();

    $diLuar = Siman2Fixture::buat(Siman2Fixture::dasar(), sys_get_temp_dir().'/siman2-luar-'.bin2hex(random_bytes(3)).'.xlsx');
    jalankanImpor($diLuar, petaStandar())->assertSuccessful();
    expect(file_exists($diLuar))->toBeTrue();
    @unlink($diLuar);
});

it('gagal jelas bila berkas atau pemetaan tidak ada', function () {
    expect(fn () => $this->artisan('siman2:impor', ['berkas' => '/tidak/ada.xlsx', '--pemetaan' => petaStandar()])->run())->toThrow(RuntimeException::class);
    expect(fn () => $this->artisan('siman2:impor', ['berkas' => Siman2Fixture::buat(Siman2Fixture::dasar()), '--pemetaan' => '/tidak/ada.csv'])->run())->toThrow(RuntimeException::class);
});

it('mengabaikan sheet yang tidak ada di berkas tanpa galat', function () {
    $berkas = Siman2Fixture::buat(['users' => Siman2Fixture::dasar()['users']]);

    jalankanImpor($berkas, petaStandar())->assertSuccessful();

    expect(User::count())->toBe(3)->and(Ruangan::count())->toBe(0);
});

it('mengurai nilai mentah SIMAN-2', function () {
    expect(Nilai::tanggal('08/02/2026 10:00')->format('Y-m-d H:i'))->toBe('2026-02-08 10:00')
        ->and(Nilai::tanggal('01 Sep 2026, 09.00')->format('Y-m-d H:i'))->toBe('2026-09-01 09:00')
        ->and(Nilai::tanggal('6 Agu 2026, 20.30')->format('Y-m-d H:i'))->toBe('2026-08-06 20:30')
        ->and(Nilai::tanggal('2026-08-30')->format('Y-m-d'))->toBe('2026-08-30')
        ->and(Nilai::tanggal('2026-08-30 14:15')->format('H:i'))->toBe('14:15')
        ->and(Nilai::tanggal('bukan tanggal'))->toBeNull()->and(Nilai::tanggal(''))->toBeNull()
        ->and(Nilai::kondisi('Baik')?->value)->toBe('B')->and(Nilai::kondisi('rusak ringan')?->value)->toBe('RR')
        ->and(Nilai::kondisi('Rusak Berat')?->value)->toBe('RB')->and(Nilai::kondisi('Hilang'))->toBeNull()
        ->and(Nilai::daftar('[1,2,3]'))->toBe(['1', '2', '3'])->and(Nilai::daftar('1, 2'))->toBe(['1', '2'])->and(Nilai::daftar(''))->toBe([])
        ->and(Nilai::daftar('["Lab 1","Lab 2"]'))->toBe(['Lab 1', 'Lab 2'])
        ->and(Nilai::peta('{"2":"Rusak Ringan","5":"Rusak Berat"}'))->toBe(['2' => 'Rusak Ringan', '5' => 'Rusak Berat'])
        ->and(Nilai::angka('Rp 1.250'))->toBe(1250)->and(Nilai::angka(''))->toBeNull()->and(Nilai::teks(123456.0))->toBe('123456')
        ->and(Nilai::bulan('November'))->toBe(11)->and(Nilai::bulan('Agt'))->toBe(8)->and(Nilai::bulan('x'))->toBeNull();
});

it('mengubah URL foto Drive lama ke tautan Drive kanonik', function () {
    $id = '1AbCdEfGhIjKlMnOpQrStUv';
    $kanonik = "https://drive.google.com/file/d/{$id}/view";

    expect(UrlFotoLama::keDrive("https://lh3.googleusercontent.com/d/{$id}"))->toBe($kanonik)
        ->and(UrlFotoLama::keDrive("https://drive.google.com/uc?id={$id}&export=view"))->toBe($kanonik)
        ->and(UrlFotoLama::keDrive("https://drive.google.com/file/d/{$id}/view?usp=sharing"))->toBe($kanonik)
        ->and(UrlFotoLama::keDrive('https://img.icons8.com/color/96/laptop--v1.png'))->toBeNull()
        ->and(UrlFotoLama::keDrive('bukan url'))->toBeNull()->and(UrlFotoLama::keDrive(null))->toBeNull()->and(UrlFotoLama::keDrive(''))->toBeNull();
});
