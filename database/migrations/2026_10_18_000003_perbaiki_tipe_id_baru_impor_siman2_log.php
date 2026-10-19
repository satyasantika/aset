<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambal migrasi 2026_10_12_000001 (create_impor_siman2_log_table): kolom `id_baru` salah
 * ditipekan `uuid`. Tabel ini log pemetaan ID lama->baru lintas sheet SIMAN-2; untuk sheet
 * `config` (setting key-value, bukan baris model), nilai "id baru" yang dicatat adalah nama key
 * (mis. "instansi_baris1"), bukan UUID — sama persis seperti `id_lama`. Di SQLite ini lolos
 * karena tipe data tidak ditegakkan; di MariaDB (`uuid` adalah tipe native sejak 10.7) nilai
 * non-UUID ditolak dengan SQLSTATE[22007]. Migrasi lama sudah dirilis dan tidak diubah di sini.
 * Idempoten: dicek dulu tipe kolom saat ini sebelum ALTER.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('impor_siman2_log')) {
            return;
        }

        $tipe = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', 'impor_siman2_log')
            ->where('COLUMN_NAME', 'id_baru')
            ->value('DATA_TYPE');

        if ($tipe === 'uuid') {
            DB::statement('ALTER TABLE impor_siman2_log MODIFY id_baru VARCHAR(100) NULL');
        }
    }

    public function down(): void
    {
        // Sengaja kosong: downgrade ke uuid akan gagal bila sudah ada id_baru non-UUID tersimpan.
    }
};
