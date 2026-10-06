<?php

namespace App\Actions\Pemeliharaan;

use App\Models\Aset;
use App\Models\User;

/**
 * Membuka tiket pemeliharaan untuk aset yang rusak (BR-10: kondisi saat kembali RR/RB).
 *
 * Stub: diisi pada F8 (pemeliharaan & lapor kerusakan). Pemanggil mengirim `$sumber` ∈ {peminjaman, inventarisasi, pic,
 * publik, civitas} dan `$sumberId`; implementasi akhir wajib idempoten per (aset, sumber, sumberId) dan ikut transaksi
 * pemanggil.
 */
class BukaTiketPemeliharaan
{
    public function handle(Aset $aset, string $sumber, ?string $sumberId, string $deskripsi, ?User $oleh = null): void
    {
        // Diisi pada F8.
    }
}
