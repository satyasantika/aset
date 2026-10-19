<?php

use App\Filament\Exports\AsetRekonsiliasiExporter;
use App\Filament\Imports\KodefikasiBarangImporter;
use App\Filament\Imports\PenggunaImporter;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): dua antrean di database, worker dinyalakan penjadwal.
 */
it('mendefinisikan koneksi database dan database-berat dengan retry_after di atas timeout worker', function () {
    $ringan = config('queue.connections.database');
    $berat = config('queue.connections.database-berat');

    expect($ringan['driver'])->toBe('database')
        ->and($ringan['after_commit'])->toBeTrue()
        ->and($ringan['retry_after'])->toBeGreaterThan(60)   // --timeout worker default
        ->and($berat['driver'])->toBe('database')
        ->and($berat['queue'])->toBe('berat')
        ->and($berat['after_commit'])->toBeTrue()
        ->and($berat['retry_after'])->toBeGreaterThan(900);  // --timeout worker berat
});

it('menjadwalkan dua worker, detak penjadwal, dan pemangkasan antrean', function () {
    $jadwal = collect(app(Schedule::class)->events())
        ->map(fn ($e) => (string) ($e->command ?? $e->description))
        ->implode("\n");

    expect($jadwal)
        ->toContain('queue:work database --queue=default --stop-when-empty')
        ->toContain('queue:work database-berat --queue=berat --stop-when-empty')
        ->toContain('detak-penjadwal')
        ->toContain('queue:prune-failed')
        ->toContain('queue:prune-batches');
});

it('mengarahkan impor dan ekspor ke koneksi database-berat dan antrean berat', function () {
    foreach ([PenggunaImporter::class, KodefikasiBarangImporter::class] as $kelas) {
        $contoh = (new ReflectionClass($kelas))->newInstanceWithoutConstructor();

        expect($contoh->getJobQueue())->toBe('berat')->and($contoh->getJobConnection())->toBe('database-berat');
    }

    $eksportir = (new ReflectionClass(AsetRekonsiliasiExporter::class))->newInstanceWithoutConstructor();

    expect($eksportir->getJobQueue())->toBe('berat')->and($eksportir->getJobConnection())->toBe('database-berat');
});
