<?php

namespace App\Console\Commands;

use App\Jobs\PeriksaTautanBerkas;
use App\Models\TautanBerkas;
use Illuminate\Console\Command;

/** Mingguan: antrekan pemeriksaan keteraksesan semua tautan berkas (foto & dokumen). */
class PeriksaTautan extends Command
{
    protected $signature = 'aset:periksa-tautan';

    protected $description = 'Antrekan pemeriksaan semua tautan berkas';

    public function handle(): int
    {
        $jumlah = 0;

        TautanBerkas::query()->select('id')->chunkById(200, function ($tautan) use (&$jumlah): void {
            foreach ($tautan as $t) {
                PeriksaTautanBerkas::dispatch($t->getKey());
                $jumlah++;
            }
        });

        $this->info("{$jumlah} tautan diantrekan untuk diperiksa.");

        return self::SUCCESS;
    }
}
