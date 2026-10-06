<?php

namespace App\Actions\Migrasi\Siman2;

use App\Models\KategoriRuangan;

/** Sheet `ruanganKategori` → `kategori_ruangan` (1:1; flag DKPS ditebak dari nama). */
class ImporKategoriRuangan implements Importer
{
    public function nama(): string
    {
        return 'ruanganKategori';
    }

    public function jalankan(Konteks $konteks, BacaXlsx $xlsx): void
    {
        foreach ($xlsx->baris('ruanganKategori') as $baris) {
            $id = Nilai::teks($baris['id'] ?? $baris['namaKategori'] ?? '');

            $konteks->proses('ruanganKategori', $id, function () use ($baris): string {
                $nama = Nilai::tidakKosong($baris['namaKategori'] ?? null) ?? throw new \InvalidArgumentException('namaKategori kosong.');

                $kategori = KategoriRuangan::query()->withTrashed()->firstOrNew(['nama' => $nama]);
                $kategori->fill([
                    'adalah_laboratorium' => $kategori->exists ? $kategori->adalah_laboratorium : str_contains(mb_strtolower($nama), 'laboratorium'),
                    'adalah_ruang_kelas' => $kategori->exists ? $kategori->adalah_ruang_kelas : str_contains(mb_strtolower($nama), 'kelas'),
                ])->save();

                return $kategori->getKey();
            });
        }
    }
}
