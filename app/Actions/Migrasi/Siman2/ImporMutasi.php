<?php

namespace App\Actions\Migrasi\Siman2;

use App\Enums\StatusMutasi;
use App\Models\Aset;
use App\Models\ImporSiman2Log;
use App\Models\Mutasi;
use App\Models\Ruangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sheet `mutasi` → `mutasi`, `mutasi_item`, `riwayat_lokasi_aset` (07-MIGRASI §3). Ruangan lewat nama; pemohon (teks)
 * dicantumkan pada alasan karena pelaku lama tidak terverifikasi (`diajukan_oleh` = null). Lokasi aset TIDAK diubah:
 * lokasi terkini sudah tercermin pada sheet inventaris; riwayat lokasi hanya mencatat peristiwa lamanya.
 */
class ImporMutasi implements Importer
{
    public function nama(): string
    {
        return 'mutasi';
    }

    public function jalankan(Konteks $konteks, BacaXlsx $xlsx): void
    {
        $ruangan = Ruangan::query()->get()->mapWithKeys(fn (Ruangan $r) => [mb_strtolower(trim($r->nama)) => $r->getKey()])->all();

        foreach ($xlsx->baris('mutasi') as $baris) {
            $idLama = Nilai::teks($baris['id'] ?? '');

            $konteks->proses('mutasi', $idLama, fn (): string => $this->impor($konteks, $baris, $idLama, $ruangan));
        }
    }

    /**
     * @param  array<string, mixed>  $baris
     * @param  array<string, string>  $ruangan
     */
    private function impor(Konteks $konteks, array $baris, string $idLama, array $ruangan): string
    {
        $asal = $ruangan[mb_strtolower(trim(Nilai::teks($baris['asal'] ?? '')))] ?? throw new \InvalidArgumentException('Ruangan asal "'.Nilai::teks($baris['asal'] ?? '').'" tidak ditemukan.');
        $tujuan = $ruangan[mb_strtolower(trim(Nilai::teks($baris['tujuan'] ?? '')))] ?? throw new \InvalidArgumentException('Ruangan tujuan "'.Nilai::teks($baris['tujuan'] ?? '').'" tidak ditemukan.');

        $status = match (mb_strtolower(Nilai::teks($baris['status'] ?? ''))) {
            'pending', 'diajukan', 'menunggu' => StatusMutasi::Diajukan,
            'disetujui' => StatusMutasi::Disetujui,
            'ditolak' => StatusMutasi::Ditolak,
            default => throw new \InvalidArgumentException('Status mutasi "'.Nilai::teks($baris['status'] ?? '').'" tidak dikenal.'),
        };

        $asetIds = $this->resolusiAset($konteks, $baris);

        if ($asetIds === []) {
            throw new \InvalidArgumentException('Tidak ada aset yang dapat dipetakan untuk mutasi ini (idBarang/kodeBarang).');
        }

        $tanggal = Nilai::tanggal($baris['tanggal'] ?? null);
        $pemohon = Nilai::tidakKosong($baris['pemohon'] ?? null);
        $alasan = trim((Nilai::tidakKosong($baris['alasan'] ?? null) ?? 'Mutasi (impor SIMAN-2)').($pemohon ? " [Pemohon SIMAN-2: {$pemohon}]" : ''));

        $idBaru = $konteks->idBaru('mutasi', $idLama);
        $mutasi = ($idBaru ? Mutasi::query()->find($idBaru) : null) ?? new Mutasi;

        $mutasi->fill([
            'nomor' => $mutasi->nomor ?? 'MUT-S2-'.str_pad($idLama, 5, '0', STR_PAD_LEFT),
            'ruangan_asal_id' => $asal,
            'ruangan_tujuan_id' => $tujuan,
            'alasan' => $alasan,
            'status' => $status,
            'diajukan_oleh' => null,
            'diputuskan_oleh' => null,
            'diputuskan_pada' => $status === StatusMutasi::Diajukan ? null : $tanggal,
        ]);

        if ($tanggal !== null) {
            $mutasi->created_at = $tanggal;
        }

        $mutasi->save();
        $mutasi->aset()->syncWithoutDetaching($asetIds);

        if ($status === StatusMutasi::Disetujui) {
            foreach ($asetIds as $asetId) {
                $ada = DB::table('riwayat_lokasi_aset')->where('aset_id', $asetId)->where('mutasi_id', $mutasi->getKey())->exists();

                if (! $ada) {
                    DB::table('riwayat_lokasi_aset')->insert([
                        'id' => (string) Str::uuid7(), 'aset_id' => $asetId, 'dari_ruangan_id' => $asal, 'ke_ruangan_id' => $tujuan,
                        'sumber' => 'migrasi', 'mutasi_id' => $mutasi->getKey(), 'oleh' => null, 'created_at' => $tanggal ?? now(),
                    ]);
                }
            }
        }

        return $mutasi->getKey();
    }

    /**
     * `tipeMutasi = unit` → unit ber-indeks dari `unitIndex`/sufiks `kodeBarang`; selain itu seluruh unit baris induk.
     * Catatan R-17: setelah mutasi unit di SIMAN-2 nomor unit bergeser; pemetaan ini sebaik data sumber.
     *
     * @param  array<string, mixed>  $baris
     * @return list<string>
     */
    private function resolusiAset(Konteks $konteks, array $baris): array
    {
        $idBarang = Nilai::teks($baris['idBarang'] ?? '');
        $unit = mb_strtolower(Nilai::teks($baris['tipeMutasi'] ?? '')) === 'unit';

        if ($unit) {
            $indeks = Nilai::angka($baris['unitIndex'] ?? null)
                ?? (preg_match('/-(\d+)$/', Nilai::teks($baris['kodeBarang'] ?? ''), $m) ? (int) $m[1] : 1);
            $id = $konteks->idBaru('inventaris_unit', "{$idBarang}#{$indeks}");

            return $id && Aset::withTrashed()->whereKey($id)->exists() ? [$id] : [];
        }

        return ImporSiman2Log::query()
            ->where('sheet', 'inventaris_unit')->where('id_lama', 'like', $idBarang.'#%')->where('status', ImporSiman2Log::OK)
            ->pluck('id_baru')->filter()->values()->all();
    }
}
