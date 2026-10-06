<?php

use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(PeranDanIzinSeeder::class));

function penggunaDenganPeran(string $peran, bool $aktif = true): User
{
    $user = User::factory()->create(['aktif' => $aktif]);
    $user->assignRole($peran);

    return $user;
}

it('seeder bersifat idempoten', function () {
    $this->seed(PeranDanIzinSeeder::class);
    $this->seed(PeranDanIzinSeeder::class);

    expect(Role::count())->toBe(6)
        ->and(Permission::count())->toBe(count(PeranDanIzinSeeder::IZIN));
});

it('menerapkan matriks permission PRD §3.1 per peran', function (string $peran, array $diizinkan) {
    $user = penggunaDenganPeran($peran);

    foreach (PeranDanIzinSeeder::IZIN as $izin) {
        expect($user->can($izin))->toBe(in_array($izin, $diizinkan, true) || $peran === 'super-admin', "$peran → $izin");
    }
})->with(fn () => collect(PeranDanIzinSeeder::peta())->map(fn ($izin, $peran) => [$peran, $izin])->all());

it('mengizinkan semua aksi untuk super-admin lewat Gate::before', function () {
    $user = User::factory()->create();
    $user->assignRole('super-admin');

    expect($user->can('izin-yang-tidak-terdaftar'))->toBeTrue();
});

it('hanya menjaga Horizon untuk super-admin', function () {
    expect(penggunaDenganPeran('super-admin')->can('viewHorizon'))->toBeTrue()
        ->and(penggunaDenganPeran('admin-bmn')->can('viewHorizon'))->toBeFalse();
});

it('membatasi akses panel: aktif dan bukan hanya civitas', function (string $peran, bool $aktif, bool $boleh) {
    $user = penggunaDenganPeran($peran, $aktif);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBe($boleh);
})->with([
    'admin aktif' => ['admin-bmn', true, true],
    'pic aktif' => ['pic-ruangan', true, true],
    'pimpinan aktif' => ['pimpinan', true, true],
    'admin nonaktif' => ['admin-bmn', false, false],
    'civitas aktif' => ['civitas', true, false],
]);

it('menolak pengguna tanpa peran masuk panel', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('memvalidasi surel domain unsil.ac.id', function (string $surel, bool $lolos) {
    $v = Validator::make(['surel' => $surel], ['surel' => [new SurelDomainUnsil]]);

    expect($v->passes())->toBe($lolos);
})->with([
    ['budi@unsil.ac.id', true],
    ['Budi@UNSIL.AC.ID', true],
    ['budi@gmail.com', false],
    ['budi@unsil.ac.id.evil.com', false],
]);
