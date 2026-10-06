<?php

namespace Database\Seeders;

use App\Models\KategoriRuangan;
use Illuminate\Database\Seeder;
use Spatie\SimpleExcel\SimpleExcelReader;

/** DATA CONTOH: ganti dengan data resmi fakultas (CSV di database/seeders/data/kategori_ruangan.csv). Idempoten. */
class KategoriRuanganSeeder extends Seeder
{
    public function run(): void
    {
        SimpleExcelReader::create(database_path('seeders/data/kategori_ruangan.csv'))
            ->getRows()
            ->each(function (array $baris) {
                KategoriRuangan::query()->updateOrCreate(['nama' => $baris['nama']], ['adalah_laboratorium' => (bool) $baris['adalah_laboratorium'], 'adalah_ruang_kelas' => (bool) $baris['adalah_ruang_kelas']]);
            });
    }
}
