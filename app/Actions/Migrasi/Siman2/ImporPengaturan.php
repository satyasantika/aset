<?php

namespace App\Actions\Migrasi\Siman2;

use App\Support\Pengaturan;

/** Sheet `config` → `pengaturan` (identitas instansi, penandatangan). Bentuk sheet: kolom key/value atau satu baris. */
class ImporPengaturan implements Importer
{
    /** Kunci lama → kunci baru. */
    private const PETA = [
        'instansi_baris1' => 'instansi_baris1',
        'instansi_baris2' => 'instansi_baris2',
        'nama_kampus' => 'nama_unit', // di SIMAN-2 kolom ini berisi nama fakultas
        'alamat' => 'alamat',
        'kontak' => 'kontak',
        'kota_surat' => 'kota_surat',
        'nama_kasubag' => 'penandatangan_nama',
        'nip_kasubag' => 'penandatangan_nip',
        'nama_penanggungjawab' => 'penanggung_jawab_nama',
        'nip_penanggungjawab' => 'penanggung_jawab_nip',
    ];

    public function nama(): string
    {
        return 'config';
    }

    public function jalankan(Konteks $konteks, BacaXlsx $xlsx): void
    {
        $nilai = $this->bacaPasangan($xlsx);

        foreach (self::PETA as $lama => $baru) {
            if (! array_key_exists($lama, $nilai)) {
                continue;
            }

            $konteks->proses('config', $lama, function () use ($baru, $nilai, $lama): string {
                Pengaturan::simpan($baru, Nilai::teks($nilai[$lama]));

                return $baru;
            });
        }

        foreach (['logo_kiri', 'logo_kanan', 'login_sliders'] as $lama) {
            if (Nilai::tidakKosong($nilai[$lama] ?? null) !== null) {
                $konteks->peringatan('config', $lama, 'Gambar tidak dimigrasikan; pasang ulang sebagai tautan/aset statis.');
            }
        }

        if (str_contains(mb_strtoupper(Nilai::teks($nilai['instansi_baris1'] ?? '')), 'KEMENTRIAN')) {
            $konteks->peringatan('config', 'instansi_baris1', 'Ejaan "KEMENTRIAN" pada kop perlu diverifikasi (seharusnya "KEMENTERIAN").');
        }
    }

    /** @return array<string, mixed> */
    private function bacaPasangan(BacaXlsx $xlsx): array
    {
        $baris = $xlsx->semua('config');
        $pertama = $baris->first() ?? [];

        $kunci = collect(['key', 'kunci'])->first(fn (string $k) => array_key_exists($k, $pertama));
        $nilai = collect(['value', 'nilai'])->first(fn (string $k) => array_key_exists($k, $pertama));

        if ($kunci !== null && $nilai !== null) {
            return $baris->mapWithKeys(fn (array $b) => [Nilai::teks($b[$kunci]) => $b[$nilai] ?? null])->all();
        }

        return $pertama;
    }
}
