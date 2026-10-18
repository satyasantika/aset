<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran transaksi `JENIS-TAHUN-0001` bebas tabrakan (STANDAR-TEKNIS §1b): baris penghitung `nomor:{jenis}:{tahun}`
 * di tabel `penomoran` dikunci dengan `lockForUpdate` di dalam transaksi; nomor dihitung dan baris disimpan dalam
 * transaksi yang sama (nomor tidak terlompat bila batal). Kolom `nomor` bersifat UNIQUE sebagai pengaman akhir.
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
        $kunci = "nomor:{$jenis}:{$tahun}";

        return LockAset::satu($kunci, function () use ($kunci, $model, $awalan, $digit, $simpan) {
            // baris sudah terkunci oleh LockAset::satu; nomor terbesar yang sudah ada menyemai penghitung data lama
            $tercatat = (int) DB::table('penomoran')->where('kunci', $kunci)->value('terakhir');
            $terakhir = $model::query()->withoutGlobalScopes()->where('nomor', 'like', $awalan.'%')->max('nomor');
            $ada = $terakhir ? (int) substr((string) $terakhir, strlen($awalan)) : 0;
            $urut = max($tercatat, $ada) + 1;

            DB::table('penomoran')->where('kunci', $kunci)->update(['terakhir' => $urut]);

            return $simpan($awalan.str_pad((string) $urut, $digit, '0', STR_PAD_LEFT));
        });
    }
}
