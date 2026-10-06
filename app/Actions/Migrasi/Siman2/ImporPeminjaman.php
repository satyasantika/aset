<?php

namespace App\Actions\Migrasi\Siman2;

use App\Enums\JenisPeminjam;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\LabelLama;
use App\Models\Peminjaman;
use App\Support\FormatLabelLama;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Sheet `peminjaman` → `peminjaman` + `peminjaman_item`: dikelompokkan per `kodeTransaksi`; `kodeUnit` → `label_lama` →
 * aset (cadangan: idBarang + unitIndex). Nomor baru `PJM-S2-…`, nomor lama dicantumkan pada keperluan. Pelaku
 * (`dicatatOleh`) berupa teks → `dicatat_oleh` null dan dicatat di keperluan.
 */
class ImporPeminjaman implements Importer
{
    public function nama(): string
    {
        return 'peminjaman';
    }

    public function jalankan(Konteks $konteks, BacaXlsx $xlsx): void
    {
        $kelompok = $xlsx->semua('peminjaman')->groupBy(fn (array $b) => Nilai::tidakKosong($b['kodeTransaksi'] ?? null) ?? 'baris-'.Nilai::teks($b['id'] ?? ''));

        foreach ($kelompok as $kodeTransaksi => $baris) {
            $konteks->proses('peminjaman', (string) $kodeTransaksi, fn (): string => $this->impor($konteks, (string) $kodeTransaksi, $baris->all()));
        }
    }

    /** @param  list<array<string, mixed>>  $baris */
    private function impor(Konteks $konteks, string $kodeTransaksi, array $baris): string
    {
        $pertama = $baris[0];

        $status = match (mb_strtolower(Nilai::teks($pertama['status'] ?? ''))) {
            'dipinjam' => StatusPeminjaman::Dipinjam,
            'dikembalikan' => StatusPeminjaman::Dikembalikan,
            default => throw new \InvalidArgumentException('Status peminjaman "'.Nilai::teks($pertama['status'] ?? '').'" tidak dikenal (Dipinjam, Dikembalikan).'),
        };

        $mulai = Nilai::tanggal($pertama['tanggalPinjam'] ?? null) ?? throw new \InvalidArgumentException('tanggalPinjam kosong/tidak dikenal.');
        $rencana = $this->rencanaKembali($konteks, $kodeTransaksi, $mulai, $pertama['tanggalRencanaKembali'] ?? null);
        $kembali = Nilai::tanggal($pertama['tanggalKembaliAktual'] ?? null);

        if ($status === StatusPeminjaman::Dikembalikan && $kembali === null) {
            $kembali = $rencana;
            $konteks->peringatan('peminjaman', $kodeTransaksi, 'Tanggal kembali aktual kosong; memakai rencana kembali.');
        }

        $item = [];
        $galat = [];

        foreach ($baris as $b) {
            $aset = $this->resolusiAset($konteks, $b);

            if ($aset === null) {
                $galat[] = 'Unit "'.Nilai::teks($b['kodeUnit'] ?? '').'" tidak dapat dipetakan ke aset.';
            } else {
                $item[] = [$aset, $b];
            }
        }

        if ($galat !== []) {
            throw new \InvalidArgumentException(implode(' ', $galat));
        }

        $pencatat = Nilai::tidakKosong($pertama['dicatatOleh'] ?? null);
        $keperluan = trim((Nilai::tidakKosong($pertama['keperluan'] ?? null) ?? 'Peminjaman (impor SIMAN-2)')
            ." [Nomor lama: {$kodeTransaksi}".($pencatat ? "; dicatat oleh: {$pencatat}" : '').']');

        $idBaru = $konteks->idBaru('peminjaman', $kodeTransaksi);
        $peminjaman = ($idBaru ? Peminjaman::query()->find($idBaru) : null) ?? new Peminjaman;

        $peminjaman->fill([
            'nomor' => $peminjaman->nomor ?? 'PJM-S2-'.Str::limit(preg_replace('/[^A-Za-z0-9]+/', '-', $kodeTransaksi) ?: 'X', 20, ''),
            'jenis_peminjam' => JenisPeminjam::Civitas,
            'peminjam_user_id' => null,
            'nama_peminjam' => Nilai::tidakKosong($pertama['peminjam'] ?? null) ?? 'Tanpa nama',
            'kontak_peminjam' => Nilai::tidakKosong($pertama['kontakPeminjam'] ?? null),
            'unit_peminjam' => null,
            'keperluan' => $keperluan,
            'mulai' => $mulai,
            'rencana_kembali' => $rencana,
            'status' => $status,
            'diserahkan_pada' => $mulai,
            'dikembalikan_pada' => $status === StatusPeminjaman::Dikembalikan ? $kembali : null,
            'dicatat_oleh' => null,
        ]);
        $peminjaman->created_at = $mulai;
        $peminjaman->save();

        foreach ($item as [$aset, $b]) {
            $kondisiKembali = $status === StatusPeminjaman::Dikembalikan ? Nilai::kondisi($b['kondisiSaatKembali'] ?? null) : null;

            $peminjaman->item()->updateOrCreate(['aset_id' => $aset->getKey()], [
                'kondisi_saat_pinjam' => $aset->kondisi,
                'kondisi_saat_kembali' => $kondisiKembali,
            ]);
        }

        return $peminjaman->getKey();
    }

    private function rencanaKembali(Konteks $konteks, string $kode, CarbonInterface $mulai, mixed $mentah): CarbonInterface
    {
        $teks = Nilai::teks($mentah);
        $rencana = Nilai::tanggal($mentah);

        if ($rencana === null) {
            $konteks->peringatan('peminjaman', $kode, 'Rencana kembali kosong/tidak dikenal; memakai akhir hari peminjaman.');

            return $mulai->copy()->endOfDay();
        }

        // tanggal tanpa jam → akhir hari agar tidak dianggap terlambat lebih awal
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $teks) || preg_match('~^\d{1,2}/\d{1,2}/\d{4}$~', $teks)) {
            $rencana = $rencana->copy()->endOfDay();
        }

        if ($rencana->lessThanOrEqualTo($mulai)) {
            $konteks->peringatan('peminjaman', $kode, 'Rencana kembali tidak setelah tanggal pinjam; memakai akhir hari peminjaman.');

            return $mulai->copy()->endOfDay();
        }

        return $rencana;
    }

    /** @param  array<string, mixed>  $baris */
    private function resolusiAset(Konteks $konteks, array $baris): ?Aset
    {
        $kodeUnit = Nilai::teks($baris['kodeUnit'] ?? '');

        foreach (FormatLabelLama::kandidat($kodeUnit) as $kandidat) {
            $label = LabelLama::query()->where('teks', $kandidat)->first();

            if ($label !== null && ($aset = Aset::withTrashed()->find($label->aset_id)) !== null) {
                return $aset;
            }
        }

        $idBarang = Nilai::teks($baris['idBarang'] ?? '');
        $indeks = Nilai::angka($baris['unitIndex'] ?? null);

        if ($idBarang !== '' && $indeks !== null && ($id = $konteks->idBaru('inventaris_unit', "{$idBarang}#{$indeks}")) !== null) {
            return Aset::withTrashed()->find($id);
        }

        return null;
    }
}
