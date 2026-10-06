<?php

use App\Filament\Resources\Gedung\Pages\KelolaGedung;
use App\Filament\Resources\KategoriRuangan\Pages\KelolaKategoriRuangan;
use App\Filament\Resources\Prodi\Pages\KelolaProdi;
use App\Models\Gedung;
use App\Models\KategoriRuangan;
use App\Models\Prodi;
use App\Models\User;
use App\Support\CacheMaster;
use Database\Seeders\GedungSeeder;
use Database\Seeders\KategoriRuanganSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\ProdiSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function masuk(string $peran): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');
    test()->actingAs($user);

    return $user;
}

it('membuat, mengubah, dan menghapus (soft) gedung', function () {
    masuk('admin-bmn');

    Livewire::test(KelolaGedung::class)
        ->callAction('create', ['kode' => 'GD-X', 'nama' => 'Gedung X', 'alamat' => 'Jl. Contoh'])
        ->assertHasNoFormErrors();

    $gedung = Gedung::query()->where('kode', 'GD-X')->firstOrFail();

    Livewire::test(KelolaGedung::class)
        ->callAction(TestAction::make('edit')->table($gedung), ['nama' => 'Gedung X Baru'])
        ->callAction(TestAction::make(DeleteAction::class)->table($gedung));

    expect($gedung->refresh()->nama)->toBe('Gedung X Baru')->and($gedung->trashed())->toBeTrue();
});

it('menolak kode gedung ganda', function () {
    masuk('admin-bmn');
    Gedung::query()->create(['kode' => 'GD-A', 'nama' => 'A']);

    Livewire::test(KelolaGedung::class)
        ->callAction('create', ['kode' => 'GD-A', 'nama' => 'Lain'])
        ->assertHasFormErrors(['kode']);
});

it('mengelola kategori ruangan dengan flag DKPS', function () {
    masuk('super-admin');

    Livewire::test(KelolaKategoriRuangan::class)
        ->callAction('create', ['nama' => 'Lab Komputer', 'adalah_laboratorium' => true, 'adalah_ruang_kelas' => false])
        ->assertHasNoFormErrors();

    expect(KategoriRuangan::query()->where('nama', 'Lab Komputer')->first())
        ->adalah_laboratorium->toBeTrue()
        ->adalah_ruang_kelas->toBeFalse();
});

it('mengelola prodi dengan kode eksternal', function () {
    masuk('admin-bmn');

    Livewire::test(KelolaProdi::class)
        ->callAction('create', ['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'kode_eksternal' => '84202'])
        ->assertHasNoFormErrors();

    expect(Prodi::query()->where('kode', 'PMAT')->value('kode_eksternal'))->toBe('84202');
});

it('menutup master data bagi peran tanpa master.kelola', function (string $peran, string $url) {
    masuk($peran);

    $this->get($url)->assertForbidden();
})->with(function () {
    foreach (['pic-ruangan', 'pimpinan', 'pejabat-penatausahaan', 'civitas'] as $peran) {
        foreach (['/admin/gedung', '/admin/kategori-ruangan', '/admin/prodi'] as $url) {
            yield "$peran $url" => [$peran, $url];
        }
    }
});

it('membersihkan cache master saat data berubah', function () {
    Cache::flush();
    $daftar = CacheMaster::ambil('gedung', fn () => Gedung::query()->pluck('nama')->all());
    expect($daftar)->toBe([])->and(Cache::has('aset:master:gedung'))->toBeTrue();

    Gedung::query()->create(['kode' => 'GD-C', 'nama' => 'C']);
    expect(Cache::has('aset:master:gedung'))->toBeFalse();

    CacheMaster::ambil('gedung', fn () => []);
    Gedung::query()->where('kode', 'GD-C')->firstOrFail()->delete();
    expect(Cache::has('aset:master:gedung'))->toBeFalse();
});

it('menjalankan seeder CSV secara idempoten', function () {
    foreach ([KategoriRuanganSeeder::class, ProdiSeeder::class, GedungSeeder::class] as $kelas) {
        $this->seed($kelas);
        $this->seed($kelas);
    }

    expect(KategoriRuangan::count())->toBe(10)
        ->and(KategoriRuangan::query()->where('nama', 'Laboratorium')->value('adalah_laboratorium'))->toBeTrue()
        ->and(Prodi::count())->toBe(7)
        ->and(Gedung::count())->toBe(2);
});

it('mencatat perubahan master ke jejak audit dengan pelaku', function () {
    $admin = masuk('admin-bmn');
    $gedung = Gedung::query()->create(['kode' => 'GD-Z', 'nama' => 'Z']);

    $log = DB::table('activity_log')->where('subject_id', $gedung->id)->first();
    expect($log->causer_id)->toBe($admin->id)->and($log->log_name)->toBe('gedung');
});
