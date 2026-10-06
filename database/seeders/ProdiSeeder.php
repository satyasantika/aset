<?php

namespace Database\Seeders;

use App\Models\Prodi;
use Illuminate\Database\Seeder;
use Spatie\SimpleExcel\SimpleExcelReader;

/** DATA CONTOH: ganti dengan data resmi fakultas (CSV di database/seeders/data/prodi.csv). Idempoten. */
class ProdiSeeder extends Seeder
{
    public function run(): void
    {
        SimpleExcelReader::create(database_path('seeders/data/prodi.csv'))
            ->getRows()
            ->each(function (array $baris) {
                Prodi::query()->updateOrCreate(['kode' => $baris['kode']], ['nama' => $baris['nama'], 'kode_eksternal' => $baris['kode_eksternal'] ?: null]);
            });
    }
}
