<?php

namespace App\Console\Commands;

use App\Actions\Dbr\SnapshotDbr;
use App\Enums\StatusDbr;
use App\Models\DbrVersi;
use Illuminate\Console\Command;

/** Harian: jaring pengaman BR-13 — DBR/DBL disahkan yang daftar asetnya sudah berbeda dari snapshot ditandai `perlu_diperbarui`. */
class TandaiDbrUsang extends Command
{
    protected $signature = 'aset:tandai-dbr-usang';

    protected $description = 'Tandai DBR/DBL disahkan yang isinya sudah berubah sebagai perlu diperbarui';

    public function handle(SnapshotDbr $snapshot): int
    {
        $jumlah = 0;

        DbrVersi::query()->with('ruangan')->where('status', StatusDbr::Disahkan->value)->each(function (DbrVersi $dbr) use ($snapshot, &$jumlah): void {
            $sekarang = SnapshotDbr::hashAset($snapshot->daftarAset($dbr->ruangan));

            if (($dbr->snapshot['hash_aset'] ?? null) !== $sekarang) {
                $dbr->update(['status' => StatusDbr::PerluDiperbarui]);
                $jumlah++;
            }
        });

        $this->info("{$jumlah} DBR/DBL ditandai perlu diperbarui.");

        return self::SUCCESS;
    }
}
