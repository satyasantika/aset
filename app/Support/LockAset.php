<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Lock Redis per aset (`aset:{jenis}:{aset_id}`, STANDAR §4) untuk peminjaman & mutasi. Kunci diambil terurut agar
 * tidak terjadi deadlock; dipadukan dengan `lockForUpdate` di dalam transaksi oleh pemanggil.
 */
class LockAset
{
    public const DETIK_LOCK = 10;

    public const DETIK_TUNGGU = 5;

    /**
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

        /** @var list<Lock> $kunci */
        $kunci = [];

        try {
            foreach ($ids as $id) {
                $lock = Cache::lock("aset:{$jenis}:{$id}", self::DETIK_LOCK);

                try {
                    $lock->block($tungguDetik ?? (int) config('aset.lock_tunggu_detik', self::DETIK_TUNGGU));
                } catch (LockTimeoutException) {
                    throw ValidationException::withMessages(['aset' => 'Aset sedang diproses pengguna lain. Coba lagi sebentar.']);
                }

                $kunci[] = $lock;
            }

            return $di();
        } finally {
            foreach (array_reverse($kunci) as $lock) {
                $lock->release();
            }
        }
    }
}
