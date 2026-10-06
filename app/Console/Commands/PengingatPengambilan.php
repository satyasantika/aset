<?php

namespace App\Console\Commands;

use App\Enums\StatusPeminjaman;
use App\Models\Peminjaman;
use App\Notifications\PengingatPengambilan as NotifikasiPengambilan;
use App\Support\Penerima;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/** Harian: pengajuan disetujui yang mulai besok (H-1) tetapi belum diserahkan — peminjam dan PIC diingatkan. */
class PengingatPengambilan extends Command
{
    protected $signature = 'aset:pengingat-pengambilan';

    protected $description = 'Ingatkan peminjaman disetujui yang mulai besok dan belum diserahkan';

    public function handle(): int
    {
        $besok = now()->addDay();
        $jumlah = 0;

        Peminjaman::query()->with('peminjamUser')
            ->where('status', StatusPeminjaman::Disetujui->value)
            ->whereBetween('mulai', [$besok->copy()->startOfDay(), $besok->copy()->endOfDay()])
            ->each(function (Peminjaman $p) use (&$jumlah): void {
                $penerima = Penerima::picPeminjaman($p);

                if ($p->peminjamUser?->aktif) {
                    $penerima->push($p->peminjamUser);
                }

                Notification::send($penerima->unique('id'), new NotifikasiPengambilan($p));
                $jumlah++;
            });

        $this->info("{$jumlah} pengingat pengambilan diantrekan.");

        return self::SUCCESS;
    }
}
