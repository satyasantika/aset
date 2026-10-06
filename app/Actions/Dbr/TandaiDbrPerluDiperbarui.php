<?php

namespace App\Actions\Dbr;

/**
 * Menandai DBR/DBL ruangan `perlu_diperbarui` bila isi aset di ruangan itu berubah (BR-06, BR-13).
 *
 * Stub: diisi pada F9 (DBR & inventarisasi). Dipanggil dari dalam transaksi Action yang mengubah isi ruangan,
 * jadi implementasi akhir harus ikut transaksi pemanggil dan idempoten.
 */
class TandaiDbrPerluDiperbarui
{
    public function handle(?string $ruanganId): void
    {
        // Diisi pada F9.
    }
}
