<?php

use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as UserGoogle;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    RateLimiter::clear('google-login');
    config(['services.google.aktif' => true, 'services.google.client_id' => 'id-uji', 'services.google.client_secret' => 'rahasia']);
});

function googleSebagai(string $email, array $tambahan = []): void
{
    Socialite::fake('google', UserGoogle::fake(array_merge([
        'id' => 'g-'.md5($email), 'name' => 'Nama Google', 'email' => $email, 'email_verified' => true,
    ], $tambahan)));
}

function penggunaAdmin(string $email = 'budi@unsil.ac.id', array $atribut = []): User
{
    $user = User::factory()->create(['email' => $email, ...$atribut]);
    $user->assignRole('admin-bmn');

    return $user;
}

it('fitur nonaktif: kedua rute 404 dan halaman masuk tidak memuat tombol', function () {
    config(['services.google.aktif' => false]);

    $this->get(route('auth.google.redirect'))->assertNotFound();
    $this->get(route('auth.google.callback'))->assertNotFound();
    $this->get(route('filament.admin.auth.login'))->assertOk()->assertDontSee('Masuk dengan Google');
});

it('halaman masuk memuat tombol dan tautannya', function () {
    $this->get(route('filament.admin.auth.login'))
        ->assertOk()
        ->assertSee('Masuk dengan Google')
        ->assertSee(route('auth.google.redirect', ['asal' => route('filament.admin.auth.login')]), false);
});

it('mengarahkan ke Google dengan redirect_uri bawaan', function () {
    $lokasi = $this->get(route('auth.google.redirect'))->assertRedirect()->headers->get('Location');

    expect($lokasi)->toStartWith('https://accounts.google.com/');
    parse_str(parse_url($lokasi, PHP_URL_QUERY), $q);
    expect($q['redirect_uri'])->toBe(route('auth.google.callback'));
    expect(urldecode($q['scope']))->toContain('openid');
});

it('memasukkan akun yang sudah ada dan menautkan google_id', function () {
    $user = penggunaAdmin();
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertRedirect(Filament::getPanel('admin')->getUrl());

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->google_id)->toBe('g-'.md5('budi@unsil.ac.id'));
});

it('mencocokkan surel tanpa peduli huruf', function () {
    $user = penggunaAdmin();
    googleSebagai('Budi@Unsil.ac.id');

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

it('membuat akun baru untuk surel yang belum terdaftar', function () {
    googleSebagai('baru@gmail.com');

    $this->get(route('auth.google.callback'))->assertRedirect(route('beranda'));

    $u = User::firstWhere('email', 'baru@gmail.com');
    expect($u)->not->toBeNull()
        ->and($u->name)->toBe('Nama Google')
        ->and($u->aktif)->toBeTrue()
        ->and($u->email_verified_at)->not->toBeNull()
        ->and($u->google_id)->toBe('g-'.md5('baru@gmail.com'));
    $this->assertAuthenticated();
});

it('menolak surel yang belum diverifikasi Google', function () {
    googleSebagai('x@gmail.com', ['email_verified' => false]);

    $this->get(route('auth.google.callback'))->assertSessionHas('galat_google', 'Surel akun Google Anda belum terverifikasi.');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('menolak akun yang sudah tertaut ke Google lain', function () {
    penggunaAdmin(atribut: ['google_id' => 'google-lain']);
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertSessionHas('galat_google', 'Akun ini sudah ditautkan ke akun Google lain.');

    $this->assertGuest();
});

it('menolak akun nonaktif', function () {
    penggunaAdmin(atribut: ['aktif' => false]);
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))->assertSessionHas('galat_google', 'Akun Anda dinonaktifkan. Hubungi admin.');

    $this->assertGuest();
});

it('menolak akun ber-MFA', function () {
    $user = penggunaAdmin();
    $user->forceFill(['app_authentication_secret' => 'ABCDEFGHIJKLMNOP'])->save();
    googleSebagai('budi@unsil.ac.id');

    $this->get(route('auth.google.callback'))
        ->assertSessionHas('galat_google', 'Akun ini memakai verifikasi dua langkah; masuk dengan surel dan kata sandi.');

    $this->assertGuest();
});

it('membatalkan di Google kembali ke halaman masuk', function () {
    $this->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('galat_google', 'Masuk dengan Google dibatalkan.');

    $this->assertGuest();
});

it('state OAuth tidak cocok kembali ke halaman masuk', function () {
    Socialite::fake('google', fn () => throw new InvalidStateException);

    $this->get(route('auth.google.callback'))
        ->assertSessionHas('galat_google', 'Sesi masuk Google kedaluwarsa. Silakan coba lagi.');

    $this->assertGuest();
});

it('kembali ke asal bila asal berada di dalam aplikasi, mengabaikan asal di luar aplikasi', function () {
    $this->withSession(['google_asal' => url('/halaman-x')])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(url('/halaman-x'));

    $this->withSession(['google_asal' => 'https://evil.example/'])
        ->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'));
});

it('pengguna yang sudah masuk langsung diarahkan', function () {
    $user = penggunaAdmin();

    $this->actingAs($user)->get(route('auth.google.redirect'))->assertRedirect(Filament::getPanel('admin')->getUrl());
});

it('membatasi 10 permintaan per menit per IP', function () {
    googleSebagai('x@gmail.com', ['email_verified' => false]);

    foreach (range(1, 10) as $_) {
        $this->get(route('auth.google.callback'))->assertRedirect();
    }

    $this->get(route('auth.google.callback'))->assertStatus(429);
});
