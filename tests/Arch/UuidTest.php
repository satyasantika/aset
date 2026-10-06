<?php

use Illuminate\Database\Eloquent\Concerns\HasUuids;

arch('semua model aplikasi memakai HasUuids')
    ->expect('App\Models')
    ->toUseTrait(HasUuids::class);

it('tidak memakai kunci auto-increment pada migrasi aplikasi', function () {
    // Tabel infrastruktur kerangka kerja (STANDAR-TEKNIS §4a butir 6) dan pengecualian Filament (docs/KEPUTUSAN.md).
    $dikecualikan = ['_create_jobs_table', '_create_cache_table', '_create_imports_table', '_create_exports_table', '_create_failed_import_rows_table'];
    $terlarang = '/->id\(\)|foreignId\(|(?<!uuid|Uuid)[mM]orphs\(|bigIncrements\(|\bincrements\(/';

    $pelanggaran = [];
    foreach (glob(database_path('migrations/*.php')) as $berkas) {
        if (collect($dikecualikan)->contains(fn ($nama) => str_contains($berkas, $nama))) {
            continue;
        }
        if (preg_match($terlarang, file_get_contents($berkas), $cocok)) {
            $pelanggaran[] = basename($berkas).' → '.$cocok[0];
        }
    }

    expect($pelanggaran)->toBe([]);
});
