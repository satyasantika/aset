<?php

use App\Filament\Imports\KodefikasiBarangImporter;
use App\Filament\Resources\KodefikasiBarang\Pages\KelolaKodefikasiBarang;
use App\Models\KodefikasiBarang;
use App\Models\User;
use App\Rules\KodeBarangValid;
use Database\Seeders\KodefikasiBarangSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\ImportAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminKode(string $peran = 'admin-bmn'): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');
    test()->actingAs($user);

    return $user;
}

it('mengimpor CSV kodefikasi lewat antrean impor dan memperbarui kode yang sama', function () {
    adminKode();
    KodefikasiBarang::query()->create(['kode' => '3100102001', 'uraian' => 'Lama', 'tingkat' => 5]);
    $csv = "kode,uraian,tingkat,induk_kode,kategori_lokal\n3100102001,Meja Kerja,5,3100102,Mebeler\n3100102002,Kursi,5,3100102,Mebeler\nXYZ,Salah,9,,\n";

    Livewire::test(KelolaKodefikasiBarang::class)
        ->callAction(ImportAction::class, [
            'file' => UploadedFile::fake()->createWithContent('kodefikasi.csv', $csv),
            'columnMap' => ['kode' => 'kode', 'uraian' => 'uraian', 'tingkat' => 'tingkat', 'induk_kode' => 'induk_kode', 'kategori_lokal' => 'kategori_lokal'],
        ])
        ->assertHasNoFormErrors();

    expect(KodefikasiBarang::count())->toBe(2)
        ->and(KodefikasiBarang::query()->where('kode', '3100102001')->first())
        ->uraian->toBe('Meja Kerja')
        ->kategori_lokal->toBe('Mebeler')
        ->and(KodefikasiBarang::query()->where('kode', 'XYZ')->exists())->toBeFalse();
});

it('menjalankan importer di antrean impor', function () {
    $importer = new ReflectionMethod(KodefikasiBarangImporter::class, 'getJobQueue');
    $contoh = (new ReflectionClass(KodefikasiBarangImporter::class))->newInstanceWithoutConstructor();

    expect($importer->invoke($contoh))->toBe('impor');
});

it('rule KodeBarangValid menerima hanya kode tingkat 5 yang terdaftar', function (string $kode, bool $lolos) {
    $this->seed(KodefikasiBarangSeeder::class);

    $v = Validator::make(['kode' => $kode], ['kode' => [new KodeBarangValid]]);

    expect($v->passes())->toBe($lolos);
})->with([
    'sub-sub kelompok terdaftar' => ['3100102001', true],
    'golongan (tingkat 1)' => ['3', false],
    'sub kelompok (tingkat 4)' => ['3100102', false],
    'tidak terdaftar' => ['9999999999', false],
]);

it('rule KodeBarangValid menolak nilai bukan string', function () {
    $v = Validator::make(['kode' => 123], ['kode' => [new KodeBarangValid]]);

    expect($v->passes())->toBeFalse();
});

it('seeder contoh idempoten dan bertanda CONTOH', function () {
    $this->seed(KodefikasiBarangSeeder::class);
    $this->seed(KodefikasiBarangSeeder::class);

    expect(KodefikasiBarang::count())->toBe(8)
        ->and(KodefikasiBarang::query()->where('uraian', 'not like', '[CONTOH]%')->count())->toBe(0);
});

it('kelola kodefikasi hanya untuk master.kelola', function (string $peran, bool $boleh) {
    adminKode($peran);

    $respons = $this->get('/admin/kodefikasi-barang');
    $boleh ? $respons->assertOk() : $respons->assertForbidden();
})->with([['admin-bmn', true], ['super-admin', true], ['pic-ruangan', false], ['pimpinan', false]]);

it('tidak menyimpan berkas impor di luar direktori tmp', function () {
    expect(config('livewire.temporary_file_upload.disk'))->toBe('tmp')
        ->and(config('filesystems.disks.tmp.root'))->toBe(storage_path('app/tmp'));
});
