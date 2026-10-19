<?php

use App\Models\Aset;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Gerbang skema MariaDB: guard `getDriverName() === 'mysql'` pada migrasi lama tidak berlaku untuk `mariadb`,
 * sehingga CHECK dan FULLTEXT ditambahkan oleh migrasi 2026_10_18_000002. Khusus MariaDB/MySQL.
 */
it('memiliki tiga CHECK constraint pada tabel aset', function () {
    $nama = DB::table('information_schema.CHECK_CONSTRAINTS')
        ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', 'aset')
        ->pluck('CONSTRAINT_NAME')->all();

    expect($nama)->toContain('aset_kondisi_check', 'aset_status_bmn_check', 'aset_sk_hapus_check');
});

it('memiliki indeks FULLTEXT pada aset dan kodefikasi_barang', function () {
    $indeks = fn (string $tabel) => DB::table('information_schema.STATISTICS')
        ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', $tabel)
        ->where('INDEX_TYPE', 'FULLTEXT')->pluck('INDEX_NAME')->all();

    expect($indeks('aset'))->toContain('aset_nama_merk_fulltext')
        ->and($indeks('kodefikasi_barang'))->toContain('kodefikasi_barang_uraian_fulltext');
});

it('menolak kondisi aset di luar B, RR, RB lewat CHECK', function () {
    $aset = Aset::factory()->create();

    expect(fn () => DB::table('aset')->where('id', $aset->id)->update(['kondisi' => 'ZZ']))->toThrow(QueryException::class);
});

it('mengizinkan migrasi penambal dijalankan ulang tanpa galat (idempoten)', function () {
    $migrasi = require database_path('migrations/2026_10_18_000002_tambah_check_dan_fulltext_untuk_mariadb.php');

    $migrasi->up();

    expect(true)->toBeTrue();
});
