<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * IP pelapor TIDAK disimpan mentah (BR-12). Disimpan HMAC-SHA256(IP, garam harian). Garam acak dibuat per hari di
 * Redis (cache) dan kedaluwarsa setelah 2 hari: hash hanya dapat dibandingkan dalam hari yang sama (mendeteksi
 * pelapor berulang) dan tidak dapat dipulihkan menjadi IP, juga tidak dapat dilacak lintas hari.
 */
class HashIp
{
    public const TTL_DETIK = 172800;

    public static function dari(string $ip): string
    {
        $garam = Cache::remember('aset:lapor:garam:'.now()->toDateString(), self::TTL_DETIK, fn (): string => bin2hex(random_bytes(32)));

        return hash_hmac('sha256', $ip, $garam);
    }
}
