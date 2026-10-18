<?php

namespace App\Support;

use App\Models\Aset;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seksi kritis berbasis basis data (STANDAR-TEKNIS §1b, Profil Ringan): `DB::transaction` + `lockForUpdate`,
 * tanpa kunci cache atau Redis. Kunci selalu diambil terurut agar tidak terjadi deadlock; pemanggil tetap memeriksa ulang
 * keadaan (ketersediaan, status) di dalam kunci. Kunci dilepas saat transaksi terluar selesai. Waktu tunggu mengikuti
 * `aset.lock_tunggu_detik` (innodb_lock_wait_timeout sesi); timeout/deadlock menjadi galat validasi yang ramah.
 */
class LockAset
{
    public const DETIK_TUNGGU = 5;

    private const GALAT_LOCK = [1205, 1213]; // lock wait timeout, deadlock

    /**
     * Seksi kritis bernama (penomoran, pembukaan periode, DBL): mengunci satu baris `penomoran`.
     *
     * @template T
     *
     * @param  Closure(): T  $di
     * @return T
     */
    public static function satu(string $kunci, Closure $di, ?int $tungguDetik = null): mixed
    {
        return self::dalamTransaksi('status', 'Proses serupa sedang berjalan. Coba lagi sebentar.', $tungguDetik, function () use ($kunci, $di) {
            DB::table('penomoran')->insertOrIgnore(['kunci' => $kunci, 'terakhir' => 0]);
            DB::table('penomoran')->where('kunci', $kunci)->lockForUpdate()->value('terakhir');

            return $di();
        });
    }

    /**
     * Mengunci baris aset terurut (peminjaman dan mutasi memakai kunci yang sama: satu aset diproses satu per satu).
     * `$jenis` dipertahankan agar pemanggil tidak berubah; tidak lagi membedakan kunci.
     *
     * @template T
     *
     * @param  list<string>  $idAset
     * @param  Closure(): T  $di
     * @return T
     */
    public static function dengan(string $jenis, array $idAset, Closure $di, ?int $tungguDetik = null): mixed
    {
        $ids = array_values(array_unique($idAset));
        sort($ids);

        return self::dalamTransaksi('aset', 'Aset sedang diproses pengguna lain. Coba lagi sebentar.', $tungguDetik, function () use ($ids, $di) {
            if ($ids !== []) {
                Aset::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');
            }

            return $di();
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $di
     * @return T
     */
    private static function dalamTransaksi(string $kolom, string $pesan, ?int $tungguDetik, Closure $di): mixed
    {
        $detik = max(1, $tungguDetik ?? (int) config('aset.lock_tunggu_detik', self::DETIK_TUNGGU));
        $mariadb = in_array(DB::connection()->getDriverName(), ['mariadb', 'mysql'], true);
        $semula = null;

        if ($mariadb) {
            $semula = (int) DB::scalar('select @@session.innodb_lock_wait_timeout');
            DB::statement('set session innodb_lock_wait_timeout = '.$detik);
        }

        try {
            return DB::transaction($di);
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[1] ?? null, self::GALAT_LOCK, true)) {
                throw ValidationException::withMessages([$kolom => $pesan]);
            }

            throw $e;
        } finally {
            if ($semula !== null) {
                DB::statement('set session innodb_lock_wait_timeout = '.$semula);
            }
        }
    }
}
