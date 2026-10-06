<?php

namespace App\Actions\Aset;

use App\Models\Aset;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Mengubah data induk deskriptif. Identitas (kode barang, NUP), kondisi, status, dan lokasi sengaja tidak dapat
 * diubah di sini: identitas tidak pernah dinomori ulang; kondisi/status/lokasi punya Action dan riwayat sendiri.
 */
class UbahDataAset
{
    /** @var list<string> */
    public const KOLOM_DAPAT_DIUBAH = [
        'nama', 'merk_tipe', 'spesifikasi', 'tahun_perolehan', 'tanggal_perolehan', 'nilai_perolehan',
        'sumber_perolehan', 'sumber_dana', 'nomor_dokumen_perolehan', 'penguasaan', 'dapat_dipinjam',
        'keterangan', 'kelompok_pengadaan',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Aset $aset, array $data, User $pelaku): Aset
    {
        Gate::forUser($pelaku)->authorize('update', $aset);

        $aset->update(array_intersect_key($data, array_flip(self::KOLOM_DAPAT_DIUBAH)));

        return $aset;
    }
}
