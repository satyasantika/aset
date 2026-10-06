<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * super-admin & admin-bmn wajib mengaktifkan MFA sebelum memakai panel; sebelum itu hanya halaman profil
 * (tempat penyiapan MFA) dan keluar yang dapat dibuka.
 */
class WajibMfaAdmin
{
    public const PERAN_WAJIB_MFA = ['super-admin', 'admin-bmn'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->hasAnyRole(self::PERAN_WAJIB_MFA)) {
            return $next($request);
        }

        if (MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return $next($request);
        }

        $profil = Filament::getProfileUrl();

        if ($request->url() === $profil || $request->routeIs('filament.admin.auth.logout') || $request->is('livewire/*')) {
            return $next($request);
        }

        Notification::make()->warning()->title('Aktifkan autentikasi dua langkah')
            ->body('Peran Anda wajib memakai MFA. Siapkan aplikasi autentikator di halaman profil.')->send();

        return redirect()->to($profil);
    }
}
