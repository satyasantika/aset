<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Login panel: pembatasan 5 percobaan/menit per kombinasi surel + IP, dan hanya akun aktif.
 */
class Masuk extends Login
{
    public const BATAS_PERCOBAAN = 5;

    public function authenticate(): ?LoginResponse
    {
        $kunci = $this->kunciPembatas();

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_PERCOBAAN)) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip() ?? '',
                RateLimiter::availableIn($kunci),
            ))?->send();

            return null;
        }

        RateLimiter::hit($kunci, 60);

        if (($civitas = $this->masukSebagaiCivitas()) !== null) {
            RateLimiter::clear($kunci);

            return $civitas;
        }

        $respons = parent::authenticate();

        if ($respons !== null) {
            RateLimiter::clear($kunci);
        }

        return $respons;
    }

    protected function kunciPembatas(): string
    {
        $surel = strtolower((string) ($this->data['email'] ?? ''));

        return 'masuk-admin:'.sha1($surel.'|'.request()->ip());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[\SensitiveParameter] array $data): array
    {
        return [...parent::getCredentialsFromFormData($data), 'aktif' => true];
    }

    /**
     * Civitas (hanya berperan `civitas`) tidak memakai panel, tetapi tetap harus dapat masuk untuk mengajukan peminjaman:
     * setelah kredensial valid mereka dialihkan ke `/pinjam` (halaman di luar panel).
     */
    private function masukSebagaiCivitas(): ?LoginResponse
    {
        $data = $this->form->getState();
        $user = User::query()->where('email', $data['email'] ?? '')->first();

        if ($user === null || ! $user->aktif || ! Hash::check((string) ($data['password'] ?? ''), $user->password)) {
            return null;
        }

        if ($user->roles()->where('name', '!=', 'civitas')->exists() || ! $user->hasRole('civitas')) {
            return null;
        }

        Auth::guard('web')->login($user, (bool) ($data['remember'] ?? false));
        session()->regenerate();

        return new class implements LoginResponse
        {
            public function toResponse($request): RedirectResponse
            {
                return redirect()->to(route('pinjam'));
            }
        };
    }
}
