<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
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
}
