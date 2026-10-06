<?php

namespace App\Listeners;

use App\Events\LaporanKerusakanDiterima;
use App\Notifications\LaporanKerusakanBaru;
use App\Support\Penerima;
use Illuminate\Support\Facades\Notification;

/** Laporan kerusakan baru → PIC ruangan aset (tanpa PIC: admin BMN). */
class KirimNotifikasiLaporanKerusakan
{
    public function handle(LaporanKerusakanDiterima $event): void
    {
        $tiket = $event->tiket->loadMissing('aset');

        Notification::send(Penerima::picRuangan([$tiket->aset->ruangan_id]), new LaporanKerusakanBaru($tiket));
    }
}
