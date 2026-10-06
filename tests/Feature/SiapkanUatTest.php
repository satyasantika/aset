<?php

use App\Models\Aset;
use App\Models\LabelLama;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menyiapkan akun tiap peran, dua ruangan berPIC, dan barang contoh', function () {
    $this->artisan('siman:siapkan-uat', ['--kata-sandi' => 'RahasiaUji123'])->assertSuccessful();

    expect(User::query()->where('email', 'like', 'uat.%')->count())->toBe(8)
        ->and(User::query()->where('email', 'uat.pic.lab@unsil.ac.id')->first()->ruanganDikelola)->toHaveCount(1)
        ->and(User::query()->where('email', 'uat.admin@unsil.ac.id')->first()->hasRole('admin-bmn'))->toBeTrue()
        ->and(Aset::query()->count())->toBe(35)
        ->and(Aset::query()->where('kondisi', 'RB')->count())->toBe(3)
        ->and(Aset::query()->where('dapat_dipinjam', true)->where('nama', 'Proyektor')->count())->toBe(5)
        ->and(LabelLama::query()->count())->toBe(10);
});

it('idempoten: dijalankan ulang tidak menggandakan data dan tidak mengganti kata sandi', function () {
    $this->artisan('siman:siapkan-uat', ['--kata-sandi' => 'RahasiaUji123'])->assertSuccessful();
    $hash = User::query()->where('email', 'uat.dosen@unsil.ac.id')->value('password');

    $this->artisan('siman:siapkan-uat', ['--kata-sandi' => 'LainLagi456'])->assertSuccessful();

    expect(Aset::query()->count())->toBe(35)
        ->and(User::query()->where('email', 'like', 'uat.%')->count())->toBe(8)
        ->and(User::query()->where('email', 'uat.dosen@unsil.ac.id')->value('password'))->toBe($hash);
});

it('menolak berjalan di produksi tanpa --staging', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('siman:siapkan-uat')->assertFailed();
    expect(User::query()->count())->toBe(0);
});
