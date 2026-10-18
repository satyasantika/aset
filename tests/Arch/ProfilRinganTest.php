<?php

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): contoh env tidak boleh kembali ke Redis/SQLite, MariaDB (bukan 'mysql')
 * adalah driver, dan guard driver tidak boleh melewatkan mariadb.
 */
dataset('contoh env', ['.env.example', '.env.production.example']);

it('memakai MariaDB dan driver database pada contoh env', function (string $berkas) {
    $isi = file_get_contents(base_path($berkas));

    expect($isi)
        ->toContain("\nDB_CONNECTION=mariadb\n")
        ->toContain("\nDB_HOST=host.docker.internal\n")
        ->toContain("\nDB_DATABASE=db_aset\n")
        ->toContain("\nSESSION_DRIVER=database\n")
        ->toContain("\nQUEUE_CONNECTION=database\n")
        ->toContain("\nCACHE_STORE=database\n")
        ->toContain("\nAPP_KODE=aset\n")
        ->not->toMatch('/^REDIS_/m')
        ->not->toContain('sqlite')
        ->not->toContain('_DRIVER=redis')
        ->not->toContain('QUEUE_CONNECTION=redis')
        ->not->toContain('CACHE_STORE=redis');
})->with('contoh env');

it('menguji terhadap MariaDB db_aset_testing, bukan SQLite', function () {
    $phpunit = file_get_contents(base_path('phpunit.xml'));

    expect($phpunit)
        ->toContain('name="DB_CONNECTION" value="mariadb"')
        ->toContain('name="DB_DATABASE" value="db_aset_testing"')
        ->not->toContain('sqlite');
});

it('tidak memasang Horizon', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    expect($composer['require'])->not->toHaveKey('laravel/horizon')
        ->and(file_exists(config_path('horizon.php')))->toBeFalse()
        ->and(file_exists(app_path('Providers/HorizonServiceProvider.php')))->toBeFalse()
        ->and(file_get_contents(base_path('bootstrap/providers.php')))->not->toContain('Horizon');
});

it('tidak merujuk kelas Horizon di kode aplikasi', function () {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $berkas) {
        if ($berkas->getExtension() === 'php') {
            expect(file_get_contents($berkas->getPathname()))->not->toContain('Laravel\\Horizon');
        }
    }
});

it('mensyaratkan PHP 8.5', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    expect($composer['require']['php'])->toBe('^8.5');
});

it('tidak membandingkan driver basis data hanya dengan mysql', function () {
    // di Laravel 13 driver 'mariadb' berbeda dari 'mysql': guard harus mencakup keduanya
    $berkas = glob(database_path('migrations/*.php'));

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $f) {
        if ($f->getExtension() === 'php') {
            $berkas[] = $f->getPathname();
        }
    }

    // migrasi lama yang sudah dirilis tidak diubah; kekurangannya ditambal migrasi baru
    // (2026_10_18_000002_tambah_check_dan_fulltext_untuk_mariadb.php)
    $dikecualikan = [
        '2026_10_09_000001_create_aset_tables.php',
        '2026_10_08_000003_create_kodefikasi_barang_table.php',
    ];

    foreach ($berkas as $path) {
        if (in_array(basename($path), $dikecualikan, true)) {
            continue;
        }

        expect(preg_match('/getDriverName\(\)\s*===?\s*[\'"]mysql[\'"]/', file_get_contents($path)))
            ->toBe(0, basename($path).' membandingkan getDriverName() hanya dengan mysql');
    }
});
