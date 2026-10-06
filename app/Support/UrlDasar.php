<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Aplikasi dapat dipasang di subpath di belakang reverse proxy (mis. https://supportfkip.unsil.ac.id/aset). Semua URL
 * (rute, aset Vite/Filament, endpoint Livewire) dibangun dari APP_URL beserta path-nya; skema https dipaksa bila APP_URL https.
 */
class UrlDasar
{
    public static function terapkan(): void
    {
        $url = rtrim((string) config('app.url'), '/');
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path !== '') {
            URL::forceRootUrl($url);
        }

        if (str_starts_with($url, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
