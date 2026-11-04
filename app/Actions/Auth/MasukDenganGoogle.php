<?php

namespace App\Actions\Auth;

use App\Exceptions\MasukGoogleDitolak;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as PenggunaGoogle;

/**
 * Masuk dengan Google untuk semua akun Google: cocokkan google_id lalu surel; bila belum ada, buat akun baru tanpa peran.
 */
class MasukDenganGoogle
{
    public function jalankan(PenggunaGoogle $google): User
    {
        $email = strtolower(trim((string) $google->getEmail()));
        $raw = $google instanceof AbstractUser ? $google->getRaw() : [];
        $terverifikasi = filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOL);

        if ($email === '' || ! $terverifikasi) {
            throw new MasukGoogleDitolak('Surel akun Google Anda belum terverifikasi.');
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $user = User::create([
                'name' => $google->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(40),
                'aktif' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()]);
        }

        if ($user->google_id !== null && $user->google_id !== $google->getId()) {
            throw new MasukGoogleDitolak('Akun ini sudah ditautkan ke akun Google lain.');
        }

        if (! $user->aktif) {
            throw new MasukGoogleDitolak('Akun Anda dinonaktifkan. Hubungi admin.');
        }

        if ($user->app_authentication_secret !== null) {
            throw new MasukGoogleDitolak('Akun ini memakai verifikasi dua langkah; masuk dengan surel dan kata sandi, atau gunakan \'Lupa kata sandi\' bila belum punya kata sandi.');
        }

        if ($user->google_id === null) {
            $user->forceFill(['google_id' => $google->getId()]);
        }

        if ($user->isDirty()) {
            $user->save();
        }

        return $user;
    }
}
