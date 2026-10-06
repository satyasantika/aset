<?php

namespace Database\Seeders;

use App\Models\PengaturanItem;
use App\Support\Pengaturan;
use Illuminate\Database\Seeder;

/**
 * Identitas instansi (dari config SIMAN-2), penandatangan, toggle fitur (BR-22), ambang & retensi.
 * Idempoten dan tidak menimpa nilai yang sudah diubah administrator. Nama kementerian pada kop perlu diverifikasi
 * (SIMAN-2 menulis "KEMENTRIAN PENDIDIKAN TINGGI").
 */
class PengaturanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Pengaturan::BAWAAN as $kunci => $nilai) {
            PengaturanItem::query()->firstOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
        }

        Pengaturan::bersihkan();
    }
}
