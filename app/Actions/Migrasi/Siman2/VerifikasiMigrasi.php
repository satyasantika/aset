<?php

namespace App\Actions\Migrasi\Siman2;

use App\Enums\StatusAset;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\ImporSiman2Log;
use App\Models\Peminjaman;

/**
 * Rekonsiliasi migrasi (07-MIGRASI §6): Σ jumlah vs aset, per ruangan, per kondisi, pinjaman aktif, pengguna.
 * Tanpa berkas sumber hanya angka sistem baru yang ditampilkan (status "-").
 */
class VerifikasiMigrasi
{
    /**
     * @return list<array{cek: string, sumber: int|string, baru: int|string, status: string}>
     */
    public function hitung(?BacaXlsx $xlsx = null): array
    {
        $aset = Aset::query()->where('kelompok_pengadaan', 'like', 'SIMAN2-%');
        $baris = [];
        $sumber = $xlsx?->semua('inventaris');

        $baris[] = $this->baris('Jumlah unit (Σ jumlah vs aset)', $sumber?->sum(fn (array $b) => Nilai::angka($b['jumlah'] ?? null) ?? 1), (clone $aset)->count());

        // per ruangan
        $dbPerRuangan = (clone $aset)->with('ruangan')->get()->groupBy(fn (Aset $a) => mb_strtolower((string) $a->ruangan?->nama))->map->count();
        $srcPerRuangan = $sumber?->groupBy(fn (array $b) => mb_strtolower(trim(Nilai::teks($b['lokasiBarang'] ?? ''))))
            ->map(fn ($g) => $g->sum(fn (array $b) => Nilai::angka($b['jumlah'] ?? null) ?? 1));

        $semuaRuangan = collect($srcPerRuangan?->keys() ?? [])->merge($dbPerRuangan->keys())->unique()->sort()->values();

        foreach ($semuaRuangan as $nama) {
            $baris[] = $this->baris("Ruangan: {$nama}", $srcPerRuangan?->get($nama, 0), (int) $dbPerRuangan->get($nama, 0));
        }

        // per kondisi (unit "Hilang" = status hilang)
        $srcKondisi = ['B' => 0, 'RR' => 0, 'RB' => 0, 'Hilang' => 0];

        foreach ($sumber ?? [] as $b) {
            $n = Nilai::angka($b['jumlah'] ?? null) ?? 1;
            $peta = Nilai::peta($b['unitKondisi'] ?? null);

            for ($i = 1; $i <= $n; $i++) {
                $nilai = $peta[$i] ?? Nilai::teks($b['kondisi'] ?? '');
                $hilang = mb_strtolower((string) $nilai) === 'hilang' || (mb_strtolower(Nilai::teks($b['kondisi'] ?? '')) === 'hilang' && ! isset($peta[$i]));
                $unit = Nilai::kondisi($nilai) ?? Nilai::kondisi($b['kondisi'] ?? null);
                $srcKondisi[$hilang ? 'Hilang' : ($unit !== null ? $unit->value : 'B')]++;
            }
        }

        foreach (['B', 'RR', 'RB'] as $k) {
            $baris[] = $this->baris("Kondisi {$k}", $sumber ? $srcKondisi[$k] : null, (clone $aset)->where('kondisi', $k)->where('status', '!=', StatusAset::Hilang->value)->count());
        }
        $baris[] = $this->baris('Hilang', $sumber ? $srcKondisi['Hilang'] : null, (clone $aset)->where('status', StatusAset::Hilang->value)->count());

        // pinjaman aktif
        $srcAktif = $xlsx?->semua('peminjaman')->filter(fn (array $b) => mb_strtolower(Nilai::teks($b['status'] ?? '')) === 'dipinjam')
            ->groupBy(fn (array $b) => Nilai::tidakKosong($b['kodeTransaksi'] ?? null) ?? 'baris-'.Nilai::teks($b['id'] ?? ''))->count();
        $baris[] = $this->baris('Pinjaman aktif (per transaksi)', $srcAktif, Peminjaman::query()->where('nomor', 'like', 'PJM-S2-%')->where('status', StatusPeminjaman::Dipinjam->value)->count());

        // pengguna
        $baris[] = $this->baris('Pengguna', $xlsx?->semua('users')->count(), ImporSiman2Log::query()->where('sheet', 'users')->where('status', ImporSiman2Log::OK)->count());

        $baris[] = ['cek' => 'Label perlu cetak ulang (info)', 'sumber' => '-', 'baru' => (clone $aset)->where('label_perlu_cetak_ulang', true)->count(), 'status' => '-'];
        $baris[] = ['cek' => 'Baris sumber bergalat (impor_siman2_log)', 'sumber' => '-', 'baru' => ImporSiman2Log::query()->where('status', ImporSiman2Log::GALAT)->count(), 'status' => ImporSiman2Log::query()->where('status', ImporSiman2Log::GALAT)->exists() ? 'BEDA' : 'OK'];

        return $baris;
    }

    /** @param  list<array{cek: string, sumber: int|string, baru: int|string, status: string}>  $baris */
    public function lolos(array $baris): bool
    {
        return collect($baris)->doesntContain(fn (array $b) => $b['status'] === 'BEDA');
    }

    /** @return array{cek: string, sumber: int|string, baru: int|string, status: string} */
    private function baris(string $cek, ?int $sumber, int $baru): array
    {
        return [
            'cek' => $cek,
            'sumber' => $sumber ?? '-',
            'baru' => $baru,
            'status' => $sumber === null ? '-' : ($sumber === $baru ? 'OK' : 'BEDA'),
        ];
    }
}
