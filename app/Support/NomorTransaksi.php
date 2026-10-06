<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Penomoran transaksi `JENIS-TAHUN-0001` bebas tabrakan: `Cache::lock("aset:nomor:{jenis}:{tahun}")` (STANDAR §4).
 * Nomor dihitung dan baris disimpan di dalam lock yang sama; kolom `nomor` bersifat UNIQUE sebagai pengaman akhir.
 */
class NomorTransaksi
{
    /**
     * @template T
     *
     * @param  class-string<Model>  $model  Model yang memiliki kolom `nomor`.
     * @param  Closure(string): T  $simpan  Menerima nomor baru dan menyimpan barisnya.
     * @return T
     */
    public static function buat(string $jenis, string $model, int $digit, Closure $simpan): mixed
    {
        $tahun = now()->year;
        $awalan = "{$jenis}-{$tahun}-";

        return Cache::lock("aset:nomor:{$jenis}:{$tahun}", 10)->block(5, function () use ($model, $awalan, $digit, $simpan) {
            $terakhir = $model::query()->where('nomor', 'like', $awalan.'%')->max('nomor');
            $urut = $terakhir ? ((int) substr((string) $terakhir, strlen($awalan))) + 1 : 1;

            return $simpan($awalan.str_pad((string) $urut, $digit, '0', STR_PAD_LEFT));
        });
    }
}
