<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/** Cache master data dengan kunci `aset:master:<nama>`; dibersihkan oleh model master saat berubah. */
class CacheMaster
{
    public const AWALAN = 'aset:master:';

    /** @param  Closure(): mixed  $sumber */
    public static function ambil(string $nama, Closure $sumber): mixed
    {
        return Cache::rememberForever(self::AWALAN.$nama, $sumber);
    }

    public static function bersihkan(string $nama): void
    {
        Cache::forget(self::AWALAN.$nama);
    }
}
