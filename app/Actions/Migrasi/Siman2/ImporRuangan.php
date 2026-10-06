<?php

namespace App\Actions\Migrasi\Siman2;

use App\Models\ImporSiman2Log;
use App\Models\KategoriRuangan;
use App\Models\Ruangan;
use App\Support\UrlFotoLama;
use Illuminate\Support\Str;

/**
 * Sheet `ruangan` → `ruangan` (+ foto sebagai tautan). `idRuangan` → `kode` (kosong → R-xxx). PIC ditugaskan pada
 * langkah pengguna karena membutuhkan akun yang sudah ada. namaPIC/hpPIC diabaikan (diambil dari akun).
 */
class ImporRuangan implements Importer
{
    public const LABEL_FOTO = 'Foto ruangan (SIMAN-2)';

    public function nama(): string
    {
        return 'ruangan';
    }

    public function jalankan(Konteks $konteks, BacaXlsx $xlsx): void
    {
        foreach ($xlsx->baris('ruangan') as $baris) {
            $idLama = Nilai::teks($baris['id'] ?? $baris['idRuangan'] ?? $baris['namaRuangan'] ?? '');

            $konteks->proses('ruangan', $idLama, function () use ($konteks, $baris, $idLama): string {
                $namaRuangan = Nilai::tidakKosong($baris['namaRuangan'] ?? null) ?? throw new \InvalidArgumentException('namaRuangan kosong.');
                $kode = Nilai::tidakKosong($baris['idRuangan'] ?? null) ?? 'R-'.str_pad(Str::limit(preg_replace('/\D/', '', $idLama) ?: '0', 5, ''), 3, '0', STR_PAD_LEFT);

                $idBaru = $konteks->idBaru('ruangan', $idLama);
                $ruangan = $idBaru ? Ruangan::withTrashed()->find($idBaru) : null;

                if ($ruangan === null && ($sama = Ruangan::withTrashed()->where('kode', $kode)->first()) !== null) {
                    // kode sudah ada: boleh ditautkan hanya bila bukan hasil impor baris SIMAN-2 lain
                    $pemilik = ImporSiman2Log::query()->where('sheet', 'ruangan')->where('id_baru', $sama->getKey())->value('id_lama');

                    if ($pemilik !== null && $pemilik !== $idLama) {
                        throw new \InvalidArgumentException("Kode ruangan {$kode} sudah dipakai ruangan SIMAN-2 lain (id lama {$pemilik}); perbaiki idRuangan di sumber.");
                    }

                    $ruangan = $sama;
                }

                $ruangan ??= new Ruangan;

                $kategori = null;
                if (($namaKategori = Nilai::tidakKosong($baris['kategoriRuangan'] ?? null)) !== null) {
                    $kategori = KategoriRuangan::query()->withTrashed()->firstOrCreate(['nama' => $namaKategori]);
                }

                $ruangan->fill([
                    'kode' => $kode,
                    'nama' => $namaRuangan,
                    'kategori_ruangan_id' => $kategori?->getKey() ?? $ruangan->kategori_ruangan_id,
                    'kapasitas' => Nilai::angka($baris['kapasitas'] ?? null) ?? $ruangan->kapasitas,
                ])->save();

                $this->pasangFoto($konteks, $ruangan, Nilai::tidakKosong($baris['fotoRuangan'] ?? null), $idLama);

                return $ruangan->getKey();
            });
        }
    }

    private function pasangFoto(Konteks $konteks, Ruangan $ruangan, ?string $url, string $idLama): void
    {
        if ($url === null) {
            return;
        }

        $drive = UrlFotoLama::keDrive($url);

        if ($drive === null) {
            $konteks->peringatan('ruangan', $idLama, 'Foto bukan tautan Google Drive sehingga tidak dimigrasikan: '.Str::limit($url, 80));

            return;
        }

        $tautan = $ruangan->tautanBerkas()->where('jenis', 'foto')->where('label', self::LABEL_FOTO)->first();
        $tautan === null
            ? $ruangan->tautanBerkas()->create(['jenis' => 'foto', 'label' => self::LABEL_FOTO, 'url' => $drive])
            : $tautan->update(['url' => $drive]);
    }
}
