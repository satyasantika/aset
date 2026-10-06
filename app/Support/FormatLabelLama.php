<?php

namespace App\Support;

/**
 * Urai teks label/QR lama SIMAN-2 menjadi daftar teks kandidat untuk dicocokkan ke `label_lama` (BR-17).
 * Urutan sama dengan resolveScannedCode / handleLookupBarangPublik SIMAN-2, dari yang paling spesifik:
 *  1. teks utuh apa adanya (label `KATEGORI-KODE-UNIT` yang tersimpan verbatim),
 *  2. `KODE-UNIT` dari `KATEGORI-KODE-UNIT`,
 *  3. `KODE-UNIT` dari `KODE-UNIT`,
 *  4. `KODE` tunggal (unit 1).
 * Penyimpangan disengaja dari SIMAN-2: kode tunggal tanpa unit TIDAK dipakai bila teks menyebut unit lebih dari 1
 * (mis. `935464-5`), supaya unit yang tidak terdaftar tidak diam-diam jatuh ke unit 1 (temuan R-17).
 */
class FormatLabelLama
{
    public static function normalisasi(string $teks): string
    {
        return mb_strtoupper(trim($teks));
    }

    /** @return list<string> */
    public static function kandidat(string $teks): array
    {
        $utuh = self::normalisasi($teks);

        if ($utuh === '') {
            return [];
        }

        $bagian = explode('-', $utuh);
        $jumlah = count($bagian);
        $kandidat = [$utuh];
        $unitTerakhir = (int) end($bagian) ?: 1;

        if ($jumlah >= 3) {
            $unit = (int) $bagian[2] ?: 1;
            $kandidat[] = $bagian[1].'-'.$unit;

            if ($unit === 1) {
                $kandidat[] = $bagian[1];
            }
        }

        if ($jumlah >= 2) {
            $kandidat[] = $bagian[0].'-'.$unitTerakhir;
        }

        if ($jumlah === 1 || $unitTerakhir === 1) {
            $kandidat[] = $bagian[0];
        }

        return array_values(array_unique(array_filter($kandidat, fn (string $k) => $k !== '')));
    }
}
