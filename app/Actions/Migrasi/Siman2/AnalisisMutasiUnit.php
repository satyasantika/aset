<?php

namespace App\Actions\Migrasi\Siman2;

/**
 * Temuan R-17: mutasi SATU unit di SIMAN-2 menggeser nomor unit sisa dan membuat baris baru `KODE-n`, sehingga label
 * fisik bisa menunjuk barang yang salah (07-MIGRASI §4a). Kelas ini menentukan baris inventaris dan unit yang labelnya
 * tidak dapat dipercaya:
 *  - baris induk yang pernah menjadi asal mutasi `tipeMutasi = unit` berstatus Disetujui: unit berindeks ≥ unitIndex;
 *  - baris hasil mutasi (keterangan "Hasil mutasi 1 unit ...").
 */
class AnalisisMutasiUnit
{
    /** @var array<string, int> id inventaris lama → indeks unit terkecil yang terdampak */
    private array $indukTerdampak = [];

    public static function dari(BacaXlsx $xlsx): self
    {
        $a = new self;

        foreach ($xlsx->baris('mutasi') as $baris) {
            $tipe = mb_strtolower(Nilai::teks($baris['tipeMutasi'] ?? ''));
            $status = mb_strtolower(Nilai::teks($baris['status'] ?? ''));

            if ($tipe !== 'unit' || $status !== 'disetujui') {
                continue;
            }

            $idBarang = Nilai::teks($baris['idBarang'] ?? '');
            $indeks = Nilai::angka($baris['unitIndex'] ?? null)
                ?? (preg_match('/-(\d+)$/', Nilai::teks($baris['kodeBarang'] ?? ''), $m) ? (int) $m[1] : null);

            if ($idBarang === '' || $indeks === null) {
                continue;
            }

            $a->indukTerdampak[$idBarang] = min($a->indukTerdampak[$idBarang] ?? PHP_INT_MAX, $indeks);
        }

        return $a;
    }

    /** Apakah unit ke-$indeks dari baris induk $idInventaris tidak dapat dipercaya labelnya? */
    public function unitTerdampak(string $idInventaris, int $indeks): bool
    {
        return isset($this->indukTerdampak[$idInventaris]) && $indeks >= $this->indukTerdampak[$idInventaris];
    }

    public static function barisHasilMutasi(?string $keterangan): bool
    {
        return $keterangan !== null && (bool) preg_match('/^\s*hasil mutasi 1 unit/i', $keterangan);
    }

    public function jumlahInduk(): int
    {
        return count($this->indukTerdampak);
    }
}
