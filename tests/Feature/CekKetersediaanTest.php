<?php

use App\Actions\Peminjaman\CekKetersediaan;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\Peminjaman;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function tgl(string $s): CarbonImmutable
{
    return CarbonImmutable::parse("2030-06-$s");
}

function boleh(Aset $aset, string $mulai, string $selesai, ?string $kecuali = null): bool
{
    return app(CekKetersediaan::class)->tersedia($aset, tgl($mulai), tgl($selesai), $kecuali);
}

function pinjamAset(Aset $aset, string $mulai, string $selesai, StatusPeminjaman $status = StatusPeminjaman::Disetujui): Peminjaman
{
    return Peminjaman::factory()->status($status)->rentang("2030-06-$mulai", "2030-06-$selesai")->untuk($aset)->create();
}

it('aset yang aktif, baik, dan ditandai dapat dipinjam tersedia bila tak ada peminjaman lain', function () {
    $aset = Aset::factory()->dapatDipinjam()->create();

    expect(boleh($aset, '10 08:00', '10 16:00'))->toBeTrue();
});

it('menolak rentang yang tumpang tindih dengan peminjaman disetujui atau dipinjam', function (string $mulai, string $selesai, bool $tersedia) {
    $aset = Aset::factory()->dapatDipinjam()->create();
    pinjamAset($aset, '10 09:00', '10 12:00');

    expect(boleh($aset, $mulai, $selesai))->toBe($tersedia);
})->with([
    'sama persis' => ['10 09:00', '10 12:00', false],
    'di dalam' => ['10 10:00', '10 11:00', false],
    'membungkus' => ['10 08:00', '10 13:00', false],
    'tumpang tindih awal' => ['10 08:00', '10 10:00', false],
    'tumpang tindih akhir' => ['10 11:00', '10 14:00', false],
    'selesai tepat saat mulai (bersinggungan)' => ['10 07:00', '10 09:00', true],
    'mulai tepat saat selesai (bersinggungan)' => ['10 12:00', '10 15:00', true],
    'sebelum' => ['09 08:00', '09 17:00', true],
    'sesudah' => ['11 08:00', '11 17:00', true],
]);

it('memperlakukan peminjaman dipinjam sama dengan disetujui, tetapi mengabaikan yang lain', function (StatusPeminjaman $status, bool $menahan) {
    $aset = Aset::factory()->dapatDipinjam()->create();
    pinjamAset($aset, '10 09:00', '10 12:00', $status);

    expect(boleh($aset, '10 09:30', '10 11:00'))->toBe(! $menahan);
})->with([
    [StatusPeminjaman::Disetujui, true],
    [StatusPeminjaman::Dipinjam, true],
    [StatusPeminjaman::Diajukan, false],
    [StatusPeminjaman::Ditolak, false],
    [StatusPeminjaman::Dibatalkan, false],
    [StatusPeminjaman::Dikembalikan, false],
]);

it('peminjaman dipinjam yang sudah lewat rencana kembali tetap menahan aset', function () {
    $aset = Aset::factory()->dapatDipinjam()->create();
    Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)
        ->rentang(now()->subDays(5)->toDateTimeString(), now()->subDays(3)->toDateTimeString())->untuk($aset)->create();

    expect(app(CekKetersediaan::class)->tersedia($aset, now()->addDay(), now()->addDays(2)))->toBeFalse()
        ->and(app(CekKetersediaan::class)->alasan($aset, now()->addDay(), now()->addDays(2)))->toContain('bentrok dengan PJM-');

    // tetapi rentang yang berakhir sebelum peminjaman terlambat itu dimulai tidak bentrok
    expect(app(CekKetersediaan::class)->tersedia($aset, now()->subDays(8), now()->subDays(6)))->toBeTrue();
});

it('peminjaman dikembalikan yang melewati rencana kembali tidak menahan aset', function () {
    $aset = Aset::factory()->dapatDipinjam()->create();
    Peminjaman::factory()->status(StatusPeminjaman::Dikembalikan)
        ->rentang(now()->subDays(5)->toDateTimeString(), now()->subDays(3)->toDateTimeString())->untuk($aset)->create();

    expect(app(CekKetersediaan::class)->tersedia($aset, now()->addDay(), now()->addDays(2)))->toBeTrue();
});

it('menolak aset yang melanggar BR-11', function (array $atribut, string $alasan) {
    $aset = Aset::factory()->dapatDipinjam()->create($atribut);

    expect(boleh($aset, '10 08:00', '10 16:00'))->toBeFalse()
        ->and(app(CekKetersediaan::class)->alasan($aset, tgl('10 08:00'), tgl('10 16:00')))->toContain($alasan);
})->with([
    'dalam perbaikan' => [['status' => 'dalam_perbaikan'], 'Dalam perbaikan'],
    'diusulkan hapus' => [['status' => 'diusulkan_hapus', 'kondisi' => 'RB'], 'Diusulkan hapus'],
    'hilang' => [['status' => 'hilang'], 'Hilang'],
    'rusak berat' => [['kondisi' => 'RB'], 'Rusak Berat'],
    'tidak ditandai dapat dipinjam' => [['dapat_dipinjam' => false], 'tidak ditandai dapat dipinjam'],
]);

it('rusak ringan masih boleh dipinjam', function () {
    expect(boleh(Aset::factory()->dapatDipinjam()->create(['kondisi' => 'RR']), '10 08:00', '10 16:00'))->toBeTrue();
});

it('menolak rentang terbalik atau kosong', function () {
    $aset = Aset::factory()->dapatDipinjam()->create();

    expect(boleh($aset, '10 16:00', '10 08:00'))->toBeFalse()
        ->and(boleh($aset, '10 08:00', '10 08:00'))->toBeFalse();
});

it('mengecualikan peminjaman tertentu (untuk pengecekan ulang saat persetujuan)', function () {
    $aset = Aset::factory()->dapatDipinjam()->create();
    $sendiri = pinjamAset($aset, '10 09:00', '10 12:00');

    expect(boleh($aset, '10 09:00', '10 12:00'))->toBeFalse()
        ->and(boleh($aset, '10 09:00', '10 12:00', $sendiri->id))->toBeTrue();
});

it('pastikan melempar ValidationException berisi alasan', function () {
    $aset = Aset::factory()->dapatDipinjam()->create();
    pinjamAset($aset, '10 09:00', '10 12:00');

    expect(fn () => app(CekKetersediaan::class)->pastikan($aset, tgl('10 10:00'), tgl('10 11:00')))
        ->toThrow(ValidationException::class, 'tidak tersedia');
});

it('hanya peminjaman pada aset yang sama yang menahan', function () {
    [$a, $b] = [Aset::factory()->dapatDipinjam()->create(), Aset::factory()->dapatDipinjam()->create()];
    pinjamAset($a, '10 09:00', '10 12:00');

    expect(boleh($b, '10 09:00', '10 12:00'))->toBeTrue();
});

it('sedangDipinjam hanya benar untuk peminjaman berstatus dipinjam', function () {
    $aset = Aset::factory()->dapatDipinjam()->create();
    pinjamAset($aset, '10 09:00', '10 12:00', StatusPeminjaman::Disetujui);
    expect($aset->fresh()->sedangDipinjam)->toBeFalse();

    pinjamAset($aset, '12 09:00', '12 12:00', StatusPeminjaman::Dipinjam);
    expect($aset->fresh()->sedangDipinjam)->toBeTrue();
});
