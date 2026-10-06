<?php

namespace App\Actions\Dbr;

use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Models\Aset;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\Pengaturan;

/**
 * Menyusun isi dokumen DBR/DBL (BR-13) dari data aset saat ini. Hasilnya disimpan sebagai snapshot JSON; setelah itu
 * dokumen tidak lagi bergantung pada data aset. Aset hilang/dihapus tidak termasuk (masuk daftar barang hilang/RB).
 */
class SnapshotDbr
{
    /** @return array<string, mixed> */
    public function susun(?Ruangan $ruangan, int $versi, ?User $oleh): array
    {
        $aset = $this->daftarAset($ruangan);

        return [
            'jenis' => $ruangan === null ? 'dbl' : 'dbr',
            'versi' => $versi,
            'ruangan' => $ruangan === null ? null : ['id' => $ruangan->getKey(), 'kode' => $ruangan->kode, 'nama' => $ruangan->nama, 'gedung' => $ruangan->gedung?->nama],
            'dibangkitkan_pada' => now()->toIso8601String(),
            'dibangkitkan_oleh' => $oleh === null ? null : ['id' => $oleh->getKey(), 'nama' => $oleh->name],
            'kop' => $this->kop(),
            'aset' => $aset,
            'ringkasan' => $this->ringkasan($aset),
            'hash_aset' => self::hashAset($aset),
            'penandatangan' => ['pic' => null, 'pejabat' => null],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function daftarAset(?Ruangan $ruangan): array
    {
        $query = Aset::query()->whereNotIn('status', [StatusAset::Hilang->value, StatusAset::Dihapus->value]);

        $ruangan === null
            ? $query->whereNull('ruangan_id')->whereNotNull('lokasi_lainnya')
            : $query->where('ruangan_id', $ruangan->getKey());

        return $query->orderBy('kode_barang')->orderBy('nup')->orderBy('kode_internal')->orderBy('id')->get()
            ->map(fn (Aset $a) => [
                'id' => $a->getKey(),
                'kode_barang' => $a->kode_barang,
                'nup' => $a->nup,
                'kode_internal' => $a->kode_internal,
                'nama' => $a->nama,
                'merk_tipe' => $a->merk_tipe,
                'tahun_perolehan' => $a->tahun_perolehan,
                'kondisi' => $a->kondisi->value,
                'lokasi_lainnya' => $a->lokasi_lainnya,
                'keterangan' => $a->keterangan,
            ])->values()->all();
    }

    /**
     * Sidik isi daftar: dipakai untuk memastikan yang disahkan pejabat = yang disetujui PIC.
     *
     * @param  list<array<string, mixed>>  $aset
     */
    public static function hashAset(array $aset): string
    {
        return hash('sha256', json_encode($aset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /** @return array<string, string> */
    private function kop(): array
    {
        return [
            'instansi_baris1' => (string) Pengaturan::ambil('instansi_baris1'),
            'instansi_baris2' => (string) Pengaturan::ambil('instansi_baris2'),
            'nama_unit' => (string) Pengaturan::ambil('nama_unit'),
            'alamat' => (string) Pengaturan::ambil('alamat'),
            'kontak' => (string) Pengaturan::ambil('kontak'),
            'kota_surat' => (string) Pengaturan::ambil('kota_surat'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $aset
     * @return array<string, int>
     */
    private function ringkasan(array $aset): array
    {
        $hasil = ['jumlah' => count($aset)];

        foreach (KondisiAset::cases() as $k) {
            $hasil[$k->value] = count(array_filter($aset, fn (array $a) => $a['kondisi'] === $k->value));
        }

        return $hasil;
    }
}
