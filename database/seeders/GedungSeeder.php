<?php

namespace Database\Seeders;

use App\Models\Gedung;
use Illuminate\Database\Seeder;
use Spatie\SimpleExcel\SimpleExcelReader;

/** DATA CONTOH: ganti dengan data resmi fakultas (CSV di database/seeders/data/gedung.csv). Idempoten. */
class GedungSeeder extends Seeder
{
    public function run(): void
    {
        SimpleExcelReader::create(database_path('seeders/data/gedung.csv'))
            ->getRows()
            ->each(function (array $baris) {
                Gedung::query()->updateOrCreate(['kode' => $baris['kode']], ['nama' => $baris['nama'], 'alamat' => $baris['alamat'] ?: null]);
            });
    }
}
