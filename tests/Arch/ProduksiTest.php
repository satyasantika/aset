<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Produksi Profil Ringan (STANDAR-TEKNIS §1b dan §7): basis data MariaDB NATIVE di host,
 * tanpa container database, Redis, atau Horizon; setiap container berbatas memori dan lognya dirotasi.
 */
function komposProduksi(): array
{
    return Yaml::parseFile(base_path('compose.prod.yaml'));
}

it('hanya memuat layanan app, web, scheduler, dan cadangan', function () {
    expect(array_keys(komposProduksi()['services']))->toEqualCanonicalizing(['app', 'web', 'scheduler', 'cadangan']);
});

it('tidak memuat container database, redis, atau queue', function () {
    $kompos = komposProduksi();

    foreach (['queue', 'horizon', 'redis', 'db', 'mysql', 'mariadb', 'meilisearch'] as $layanan) {
        expect($kompos['services'])->not->toHaveKey($layanan);
    }

    foreach (['redis', 'data', 'db-data'] as $volume) {
        expect($kompos['volumes'] ?? [])->not->toHaveKey($volume);
    }

    // hasil parse (tanpa komentar): tidak ada image/perintah Redis, Horizon, atau SQLite
    expect(json_encode($kompos))->not->toMatch('/horizon|redis|sqlite|mysql:|mariadb:/i');
});

it('menghubungkan container ke basis data host lewat host-gateway', function () {
    foreach (['app', 'scheduler', 'cadangan'] as $nama) {
        expect(komposProduksi()['services'][$nama]['extra_hosts'])->toContain('host.docker.internal:host-gateway');
    }

    expect(file_get_contents(base_path('.env.production.example')))->toContain("\nDB_HOST=host.docker.internal\n");
});

it('menjalankan penjadwal sebagai pemicu worker', function () {
    $penjadwal = komposProduksi()['services']['scheduler'];

    expect($penjadwal['command'])->toBe('php artisan schedule:work')
        ->and($penjadwal['stop_grace_period'])->toBe('60s');
});

it('membatasi memori dan merotasi log setiap container', function () {
    foreach (komposProduksi()['services'] as $nama => $layanan) {
        expect($layanan)->toHaveKey('mem_limit')
            ->and($layanan['logging']['driver'])->toBe('json-file')
            ->and($layanan['logging']['options'])->toBe(['max-size' => '10m', 'max-file' => '3']);
    }
});

it('menjaga Dockerfile berbasis PHP 8.5 dengan klien MariaDB tanpa Redis, Horizon, dan SQLite', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->not->toMatch('/redis|horizon|sqlite/i')
        ->and($dockerfile)->toContain('ARG PHP_VERSION=8.5')
        ->and($dockerfile)->toContain('pdo_mysql')
        ->and($dockerfile)->toContain('mariadb-client');
});

it('menyetel PHP-FPM ondemand dengan empat worker', function () {
    $pool = file_get_contents(base_path('docker/php/www.conf'));

    expect($pool)->toMatch('/^pm = ondemand$/m')
        ->and($pool)->toMatch('/^pm\.max_children = 4$/m')
        ->and($pool)->toMatch('/^pm\.process_idle_timeout = 10s$/m');
});

it('tidak menyisakan skrip SQLite di docker/', function () {
    foreach (['docker/scripts/entrypoint.sh', 'docker/scripts/cadangan.sh'] as $berkas) {
        expect(file_get_contents(base_path($berkas)))->not->toMatch('/sqlite/i');
    }
});
