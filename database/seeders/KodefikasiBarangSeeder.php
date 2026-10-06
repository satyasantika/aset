<?php

namespace Database\Seeders;

use App\Models\KodefikasiBarang;
use Illuminate\Database\Seeder;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * CONTOH — impor referensi resmi. Isi CSV hanya ilustrasi struktur; sumber referensi kodefikasi resmi
 * (PMK) dan versi yang dipakai Unsil perlu diverifikasi, lalu diimpor lewat Kodefikasi barang → Impor CSV.
 */
class KodefikasiBarangSeeder extends Seeder
{
    public function run(): void
    {
        SimpleExcelReader::create(database_path('seeders/data/kodefikasi_barang.csv'))
            ->getRows()
            ->each(function (array $baris) {
                KodefikasiBarang::query()->updateOrCreate(['kode' => (string) $baris['kode']], [
                    'uraian' => $baris['uraian'],
                    'tingkat' => (int) $baris['tingkat'],
                    'induk_kode' => $baris['induk_kode'] !== '' ? (string) $baris['induk_kode'] : null,
                    'kategori_lokal' => $baris['kategori_lokal'] !== '' ? $baris['kategori_lokal'] : null,
                ]);
            });
    }
}
