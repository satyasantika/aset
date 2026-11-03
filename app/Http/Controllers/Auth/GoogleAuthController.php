<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\MasukDenganGoogle;
use App\Exceptions\MasukGoogleDitolak;
use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as PengalihanSymfony;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): PengalihanSymfony
    {
        abort_unless(config('services.google.aktif'), 404);

        $user = Auth::user();
        if ($user instanceof User) {
            return redirect()->to($this->tujuan($user));
        }

        $asal = $request->query('asal');
        session(['google_asal' => is_string($asal) ? $asal : null]);

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request, MasukDenganGoogle $masuk): RedirectResponse
    {
        abort_unless(config('services.google.aktif'), 404);

        $asal = $request->session()->pull('google_asal');
        $asal = is_string($asal) ? $asal : null;

        if ($request->filled('error')) {
            return $this->gagal('Masuk dengan Google dibatalkan.', $asal);
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->gagal('Sesi masuk Google kedaluwarsa. Silakan coba lagi.', $asal);
        } catch (\Throwable $e) {
            report($e);

            return $this->gagal('Tidak dapat terhubung dengan Google. Silakan coba lagi.', $asal);
        }

        try {
            $user = $masuk->jalankan($google);
        } catch (MasukGoogleDitolak $e) {
            return $this->gagal($e->getMessage(), $asal);
        }

        $tujuan = $this->tujuan($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to($tujuan);
    }

    /** Akun tanpa akses panel (mis. civitas atau tanpa peran) diarahkan ke beranda. */
    private function tujuan(User $user): string
    {
        $panel = Filament::getPanel('admin');

        return $user->canAccessPanel($panel) ? $panel->getUrl() : route('beranda');
    }

    private function gagal(string $pesan, ?string $asal): RedirectResponse
    {
        $ke = ($asal !== null && str_starts_with($asal, rtrim(url('/'), '/').'/')) ? $asal : route('filament.admin.auth.login');

        return redirect()->to($ke)->with('galat_google', $pesan);
    }
}
