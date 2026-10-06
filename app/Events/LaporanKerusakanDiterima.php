<?php

namespace App\Events;

use App\Models\TiketPemeliharaan;
use Illuminate\Foundation\Events\Dispatchable;

/** Laporan kerusakan baru diterima (tiket dibuka). Didengar oleh `KirimNotifikasiLaporanKerusakan` (notifikasi ke PIC). */
class LaporanKerusakanDiterima
{
    use Dispatchable;

    public function __construct(public readonly TiketPemeliharaan $tiket) {}
}
