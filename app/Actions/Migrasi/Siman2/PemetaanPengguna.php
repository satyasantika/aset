<?php

namespace App\Actions\Migrasi\Siman2;

use RuntimeException;
use Spatie\SimpleExcel\SimpleExcelReader;

/** Tabel pemetaan manual `username,email` (docs/migrasi/pemetaan-pengguna.csv). */
class PemetaanPengguna
{
    /** @return array<string, string> username (huruf kecil) → surel */
    public static function baca(string $berkas): array
    {
        if (! is_file($berkas)) {
            throw new RuntimeException("Berkas pemetaan pengguna tidak ditemukan: {$berkas}");
        }

        $peta = [];

        foreach (SimpleExcelReader::create($berkas, 'csv')->trimHeaderRow()->getRows() as $baris) {
            $username = mb_strtolower(trim((string) ($baris['username'] ?? '')));
            $email = trim((string) ($baris['email'] ?? ''));

            if ($username !== '' && $email !== '') {
                $peta[$username] = $email;
            }
        }

        return $peta;
    }
}
