<?php

use App\Filament\Pages\Auth\EditProfil;
use App\Filament\Pages\Auth\Masuk;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    RateLimiter::clear('x');
});

function akun(string $peran, array $atribut = []): User
{
    $user = User::factory()->create(['password' => 'rahasia-123', ...$atribut]);
    $user->assignRole($peran);

    return $user;
}

function aktifkanMfa(User $user): User
{
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $user;
}

it('tidak menyediakan registrasi', function () {
    $this->get('/admin/register')->assertNotFound();
});

it('mengarahkan admin tanpa MFA ke penyiapan MFA di profil', function (string $peran) {
    $user = akun($peran);

    $this->actingAs($user)->get('/admin')->assertRedirect(Filament::getProfileUrl());
})->with(['super-admin', 'admin-bmn'])
    ->skip('WajibMfaAdmin nonaktif sementara (lihat AdminPanelProvider) sampai alur setup MFA admin pertama disiapkan');

it('membuka panel untuk admin yang sudah memakai MFA', function () {
    $user = aktifkanMfa(akun('admin-bmn'));

    $this->actingAs($user)->get('/admin')->assertOk();
});

it('tidak mewajibkan MFA untuk peran lain', function () {
    $this->actingAs(akun('pic-ruangan'))->get('/admin')->assertOk();
});

it('masih membolehkan admin tanpa MFA membuka profil untuk menyiapkannya', function () {
    $this->actingAs(akun('admin-bmn'))->get(Filament::getProfileUrl())->assertOk();
});

it('membatasi login 5 percobaan per menit per surel dan IP', function () {
    $user = akun('pic-ruangan');

    foreach (range(1, 5) as $i) {
        Livewire::test(Masuk::class)
            ->fillForm(['email' => $user->email, 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
    }

    Livewire::test(Masuk::class)
        ->fillForm(['email' => $user->email, 'password' => 'rahasia-123'])
        ->call('authenticate')
        ->assertNotified();

    $this->assertGuest();
});

it('menolak akun nonaktif walau kata sandi benar', function () {
    $user = akun('pic-ruangan', ['aktif' => false]);

    Livewire::test(Masuk::class)
        ->fillForm(['email' => $user->email, 'password' => 'rahasia-123'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('menerima login akun aktif', function () {
    $user = akun('pic-ruangan');

    Livewire::test(Masuk::class)
        ->fillForm(['email' => $user->email, 'password' => 'rahasia-123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('mengeluarkan perangkat lain saat kata sandi diganti', function () {
    $user = akun('pic-ruangan');
    $lama = $user->password;
    Event::fake([OtherDeviceLogout::class]);

    $this->actingAs($user);
    Livewire::test(EditProfil::class)
        ->fillForm([
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'baru-rahasia-456',
            'passwordConfirmation' => 'baru-rahasia-456',
            'currentPassword' => 'rahasia-123',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->password)->not->toBe($lama)
        ->and(Hash::check('baru-rahasia-456', $user->password))->toBeTrue();
    Event::assertDispatched(OtherDeviceLogout::class);
});

it('pengguna dengan wajib_ganti_sandi diarahkan ke profil', function () {
    $user = akun('pic-ruangan', ['wajib_ganti_sandi' => true]);

    $this->actingAs($user)->get('/admin')->assertRedirect(Filament::getProfileUrl());
});

it('mengganti sandi di profil mencabut kewajiban ganti sandi', function () {
    $user = akun('pic-ruangan', ['wajib_ganti_sandi' => true]);
    $this->actingAs($user);

    Livewire::test(EditProfil::class)
        ->fillForm([
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'sandi-baru-123',
            'passwordConfirmation' => 'sandi-baru-123',
            'currentPassword' => 'rahasia-123',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->wajib_ganti_sandi)->toBeFalse();
    $this->get('/admin')->assertOk();
});

it('civitas dapat masuk dan dialihkan ke /pinjam, tetapi tetap tidak dapat membuka panel', function () {
    $user = akun('civitas');

    Livewire::test(Masuk::class)
        ->fillForm(['email' => $user->email, 'password' => 'rahasia-123'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect(route('pinjam'));

    $this->assertAuthenticatedAs($user);
    $this->get('/admin')->assertForbidden();
    $this->get('/pinjam')->assertOk();
});

it('civitas dengan kata sandi salah atau nonaktif tidak masuk', function () {
    $nonaktif = akun('civitas', ['aktif' => false]);

    Livewire::test(Masuk::class)->fillForm(['email' => $nonaktif->email, 'password' => 'rahasia-123'])->call('authenticate')->assertHasFormErrors(['email']);
    Livewire::test(Masuk::class)->fillForm(['email' => akun('civitas')->email, 'password' => 'salah'])->call('authenticate')->assertHasFormErrors(['email']);

    $this->assertGuest();
});
