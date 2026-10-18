<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambal migrasi 2026_10_09_000001 (CHECK pada `aset`) dan 2026_10_08_000003 (FULLTEXT) yang hanya berjalan bila
 * nama driver sama dengan `mysql`; di Laravel 13 driver MariaDB bernama `mariadb`, sehingga constraint/indeks itu
 * terlewat. Migrasi lama sudah dirilis dan tidak diubah. Idempoten: tidak menambah bila sudah ada (mis. database
 * yang pernah dibuat dengan driver `mysql`). SQLite/driver lain: dilewati.
 */
return new class extends Migration
{
    /** @var array<string, string> nama CHECK => ekspresi */
    private const CHECK_ASET = [
        'aset_kondisi_check' => "kondisi IN ('B','RR','RB')",
        'aset_status_bmn_check' => "status_bmn <> 'tercatat' OR (kode_barang IS NOT NULL AND nup IS NOT NULL)",
        'aset_sk_hapus_check' => "status <> 'dihapus' OR nomor_sk_penghapusan IS NOT NULL",
    ];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mariadb', 'mysql'], true)) {
            return;
        }

        foreach (self::CHECK_ASET as $nama => $ekspresi) {
            if (! $this->adaCheck('aset', $nama)) {
                DB::statement("ALTER TABLE aset ADD CONSTRAINT {$nama} CHECK ({$ekspresi})");
            }
        }

        if (! $this->adaIndeks('aset', 'aset_nama_merk_fulltext')) {
            DB::statement('ALTER TABLE aset ADD FULLTEXT aset_nama_merk_fulltext (nama, merk_tipe)');
        }

        if (! $this->adaIndeks('kodefikasi_barang', 'kodefikasi_barang_uraian_fulltext')) {
            DB::statement('ALTER TABLE kodefikasi_barang ADD FULLTEXT kodefikasi_barang_uraian_fulltext (uraian)');
        }
    }

    public function down(): void
    {
        // Sengaja kosong: constraint/indeks ini milik migrasi awal; mencabutnya di sini akan merusak rollback migrasi lama.
    }

    private function adaCheck(string $tabel, string $nama): bool
    {
        return DB::table('information_schema.CHECK_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $tabel)
            ->where('CONSTRAINT_NAME', $nama)
            ->exists();
    }

    private function adaIndeks(string $tabel, string $nama): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $tabel)
            ->where('INDEX_NAME', $nama)
            ->exists();
    }
};
