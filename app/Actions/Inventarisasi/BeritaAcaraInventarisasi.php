<?php

namespace App\Actions\Inventarisasi;

use App\Enums\HasilInventarisasi as Hasil;
use App\Models\HasilInventarisasi;
use App\Models\InventarisasiRuangan;
use App\Models\PeriodeInventarisasi;
use App\Models\User;
use App\Support\Pengaturan;

/**
 * Menyusun snapshot berita acara & laporan hasil inventarisasi (LAP-05). Seluruh isi dokumen (PDF/Excel) dirender dari
 * snapshot ini sehingga tetap sama walau aset berubah sesudahnya.
 */
class BeritaAcaraInventarisasi
{
    /** @return array<string, mixed> */
    public function susun(PeriodeInventarisasi $periode, User $penutup): array
    {
        $ruangan = $periode->ruangan()->with(['ruangan', 'petugas'])->get()->sortBy(fn (InventarisasiRuangan $r) => $r->ruangan->nama)->values();
        $hasil = HasilInventarisasi::query()->with(['aset', 'inventarisasiRuangan.ruangan'])
            ->whereIn('inventarisasi_ruangan_id', $ruangan->pluck('id'))->get();

        $perRuangan = $ruangan->map(function (InventarisasiRuangan $r) use ($hasil): array {
            $h = $hasil->where('inventarisasi_ruangan_id', $r->getKey());
            $hitung = fn (Hasil $j): int => $h->where('hasil', $j)->count();
            $ditemukan = $hitung(Hasil::Ditemukan);
            $berubah = $hitung(Hasil::KondisiBerubah);
            $hilang = $hitung(Hasil::TidakDitemukan);

            return [
                'ruangan_id' => $r->ruangan_id, 'kode' => $r->ruangan->kode, 'nama' => $r->ruangan->nama,
                'petugas' => $r->petugas->pluck('name')->values()->all(),
                'status' => $r->status->value,
                'total' => $ditemukan + $berubah + $hilang,
                'ditemukan' => $ditemukan, 'kondisi_berubah' => $berubah, 'tidak_ditemukan' => $hilang, 'berlebih' => $hitung(Hasil::Berlebih),
            ];
        })->all();

        $baris = fn (Hasil $j, callable $petakan): array => $hasil->where('hasil', $j)->map($petakan)->values()->all();
        $infoAset = fn (HasilInventarisasi $h): array => [
            'hasil_id' => $h->getKey(), 'aset_id' => $h->aset_id,
            'ruangan' => $h->inventarisasiRuangan->ruangan->nama,
            'kode_barang' => $h->aset?->kode_barang, 'nup' => $h->aset?->nup, 'kode_internal' => $h->aset?->kode_internal,
            'nama' => $h->aset?->nama, 'merk_tipe' => $h->aset?->merk_tipe,
        ];

        return [
            'periode' => [
                'id' => $periode->getKey(), 'nama' => $periode->nama, 'jenis' => $periode->jenis->value,
                'mulai' => $periode->mulai->toDateString(), 'selesai_rencana' => $periode->selesai_rencana?->toDateString(),
                'dibuka_pada' => $periode->dibuka_pada?->toIso8601String(), 'ditutup_pada' => now()->toIso8601String(),
            ],
            'kop' => [
                'instansi_baris1' => (string) Pengaturan::ambil('instansi_baris1'), 'instansi_baris2' => (string) Pengaturan::ambil('instansi_baris2'),
                'nama_unit' => (string) Pengaturan::ambil('nama_unit'), 'alamat' => (string) Pengaturan::ambil('alamat'),
                'kontak' => (string) Pengaturan::ambil('kontak'), 'kota_surat' => (string) Pengaturan::ambil('kota_surat'),
            ],
            'ruangan' => $perRuangan,
            'total' => [
                'ruangan' => count($perRuangan),
                'total' => array_sum(array_column($perRuangan, 'total')),
                'ditemukan' => array_sum(array_column($perRuangan, 'ditemukan')),
                'kondisi_berubah' => array_sum(array_column($perRuangan, 'kondisi_berubah')),
                'tidak_ditemukan' => array_sum(array_column($perRuangan, 'tidak_ditemukan')),
                'berlebih' => array_sum(array_column($perRuangan, 'berlebih')),
            ],
            'tidak_ditemukan' => $baris(Hasil::TidakDitemukan, $infoAset),
            'kondisi_berubah' => $baris(Hasil::KondisiBerubah, fn (HasilInventarisasi $h): array => $infoAset($h) + [
                'kondisi_data' => $h->aset?->kondisi->value, 'kondisi_ditemukan' => $h->kondisi_ditemukan?->value,
            ]),
            'berlebih' => $baris(Hasil::Berlebih, fn (HasilInventarisasi $h): array => [
                'hasil_id' => $h->getKey(), 'ruangan' => $h->inventarisasiRuangan->ruangan->nama, 'deskripsi' => $h->deskripsi_temuan,
            ]),
            'penandatangan' => [
                'penutup' => ['id' => $penutup->getKey(), 'nama' => $penutup->name, 'nip' => $penutup->nip, 'pada' => now()->toIso8601String()],
                'pejabat' => null,
            ],
        ];
    }
}
