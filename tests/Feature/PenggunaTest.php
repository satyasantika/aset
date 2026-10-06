<?php

use App\Filament\Resources\Pengguna\Pages\CreatePengguna;
use App\Filament\Resources\Pengguna\Pages\EditPengguna;
use App\Filament\Resources\Pengguna\Pages\ListPengguna;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordFilament;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function akunBerperan(string $peran): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP'); // MFA aktif agar tidak dialihkan

    return $user;
}

function idPeran(string $nama): string
{
    return Role::query()->where('name', $nama)->value('id');
}

it('menutup halaman pengguna bagi peran tanpa izin pengguna.kelola', function (string $peran) {
    $this->actingAs(akunBerperan($peran))->get('/admin/pengguna')->assertForbidden();
})->with(['pic-ruangan', 'pimpinan', 'pejabat-penatausahaan']);

it('membuka daftar pengguna bagi admin-bmn dan super-admin', function (string $peran) {
    $this->actingAs(akunBerperan($peran));
    $lain = User::factory()->create();

    Livewire::test(ListPengguna::class)->assertCanSeeTableRecords([$lain]);
})->with(['admin-bmn', 'super-admin']);

it('super-admin membuat akun, memberi peran admin-bmn, dan mengirim surel atur kata sandi', function () {
    Notification::fake();
    $this->actingAs(akunBerperan('super-admin'));

    Livewire::test(CreatePengguna::class)
        ->fillForm(['name' => 'Siti Aminah', 'email' => 'siti@unsil.ac.id', 'nip' => '198001012005012001', 'no_hp' => '0812', 'roles' => [idPeran('admin-bmn')], 'aktif' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $baru = User::query()->where('email', 'siti@unsil.ac.id')->firstOrFail();
    expect($baru->hasRole('admin-bmn'))->toBeTrue()
        ->and($baru->aktif)->toBeTrue()
        ->and($baru->nip)->toBe('198001012005012001');
    Notification::assertSentTo($baru, ResetPasswordFilament::class);
});

it('menolak surel di luar domain unsil.ac.id dan surel ganda', function () {
    $this->actingAs(akunBerperan('super-admin'));
    $ada = User::factory()->create();

    Livewire::test(CreatePengguna::class)
        ->fillForm(['name' => 'X', 'email' => 'x@gmail.com'])
        ->call('create')
        ->assertHasFormErrors(['email']);

    Livewire::test(CreatePengguna::class)
        ->fillForm(['name' => 'X', 'email' => $ada->email])
        ->call('create')
        ->assertHasFormErrors(['email']);
});

it('admin-bmn tidak dapat memberi peran super-admin atau admin-bmn', function (string $peranIstimewa) {
    $this->actingAs(akunBerperan('admin-bmn'));

    Livewire::test(CreatePengguna::class)
        ->fillForm(['name' => 'Licik', 'email' => 'licik@unsil.ac.id', 'roles' => [idPeran($peranIstimewa)]])
        ->call('create')
        ->assertHasFormErrors(['roles']);

    expect(User::query()->where('email', 'licik@unsil.ac.id')->exists())->toBeFalse();
})->with(['super-admin', 'admin-bmn']);

it('admin-bmn dapat menugaskan peran pic-ruangan', function () {
    Notification::fake();
    $this->actingAs(akunBerperan('admin-bmn'));

    Livewire::test(CreatePengguna::class)
        ->fillForm(['name' => 'Laboran', 'email' => 'laboran@unsil.ac.id', 'roles' => [idPeran('pic-ruangan')]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'laboran@unsil.ac.id')->first()->hasRole('pic-ruangan'))->toBeTrue();
});

it('admin-bmn tidak dapat mengubah akun super-admin atau admin-bmn', function (string $peran) {
    $this->actingAs(akunBerperan('admin-bmn'));
    $target = akunBerperan($peran);

    expect(auth()->user()->can('update', $target))->toBeFalse();
    $this->get('/admin/pengguna/'.$target->id.'/edit')->assertForbidden();
})->with(['super-admin', 'admin-bmn']);

it('menonaktifkan akun lewat form ubah', function () {
    $this->actingAs(akunBerperan('admin-bmn'));
    $target = User::factory()->create();
    $target->assignRole('pic-ruangan');

    Livewire::test(EditPengguna::class, ['record' => $target->getRouteKey()])
        ->fillForm(['aktif' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($target->refresh()->aktif)->toBeFalse();
});

it('tidak pernah mengizinkan penghapusan akun', function () {
    $target = User::factory()->create();

    expect(akunBerperan('admin-bmn')->can('delete', $target))->toBeFalse();
});
