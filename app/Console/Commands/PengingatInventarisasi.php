<?php

namespace App\Console\Commands;

use App\Notifications\PengingatInventarisasi as NotifikasiInventarisasi;
use App\Support\Penerima;
use App\Support\PeringatanInventarisasi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/** Bulanan (BR-15): admin BMN dan pejabat diingatkan bila inventarisasi terakhir yang disahkan melewati ambang. */
class PengingatInventarisasi extends Command
{
    protected $signature = 'aset:pengingat-inventarisasi';

    protected $description = 'Ingatkan jadwal inventarisasi bila melewati ambang (BR-15)';

    public function handle(): int
    {
        $peringatan = PeringatanInventarisasi::cek();

        if ($peringatan === null) {
            $this->info('Inventarisasi masih dalam ambang; tidak ada pengingat.');

            return self::SUCCESS;
        }

        Notification::send(Penerima::peran('admin-bmn', 'pejabat-penatausahaan')->unique('id'), new NotifikasiInventarisasi($peringatan['pesan']));
        $this->warn($peringatan['pesan']);

        return self::SUCCESS;
    }
}
