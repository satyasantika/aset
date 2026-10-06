<?php

namespace App\Console\Commands;

use App\Enums\StatusPeminjaman;
use App\Models\Peminjaman;
use App\Notifications\PeminjamanTerlambat;
use App\Support\Penerima;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/** Harian: peminjam dan PIC diingatkan untuk pinjaman yang lewat rencana kembali (BR-09). Maks. satu pengingat per pinjaman per hari. */
class PengingatTerlambat extends Command
{
    protected $signature = 'aset:pengingat-terlambat';

    protected $description = 'Kirim pengingat peminjaman yang melewati batas pengembalian';

    public function handle(): int
    {
        $jumlah = 0;

        Peminjaman::query()->with('peminjamUser')
            ->where('status', StatusPeminjaman::Dipinjam->value)->where('rencana_kembali', '<', now())
            ->each(function (Peminjaman $p) use (&$jumlah): void {
                if (! Cache::add("aset:pengingat-terlambat:{$p->getKey()}:".now()->toDateString(), 1, now()->addDay())) {
                    return;
                }

                $penerima = Penerima::picPeminjaman($p);

                if ($p->peminjamUser?->aktif) {
                    $penerima->push($p->peminjamUser);
                }

                Notification::send($penerima->unique('id'), new PeminjamanTerlambat($p));
                $jumlah++;
            });

        $this->info("{$jumlah} pengingat keterlambatan diantrekan.");

        return self::SUCCESS;
    }
}
