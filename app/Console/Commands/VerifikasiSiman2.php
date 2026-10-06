<?php

namespace App\Console\Commands;

use App\Actions\Migrasi\Siman2\BacaXlsx;
use App\Actions\Migrasi\Siman2\VerifikasiMigrasi;
use Illuminate\Console\Command;

class VerifikasiSiman2 extends Command
{
    protected $signature = 'siman2:verifikasi {berkas? : XLSX sumber untuk dibandingkan (tanpa berkas: hanya angka sistem baru)}';

    protected $description = 'Rekonsiliasi hasil migrasi SIMAN-2 (docs/07-MIGRASI-DATA.md §6): jumlah unit, per ruangan, per kondisi, pinjaman aktif';

    public function handle(VerifikasiMigrasi $verifikasi): int
    {
        $berkas = $this->argument('berkas');
        $baris = $verifikasi->hitung($berkas ? new BacaXlsx((string) $berkas) : null);

        $this->table(['Cek', 'Sumber', 'Sistem baru', 'Status'], array_map(fn (array $b) => [$b['cek'], $b['sumber'], $b['baru'], $b['status']], $baris));

        if ($berkas === null) {
            $this->warn('Tanpa berkas sumber: perbandingan tidak dilakukan. Jalankan dengan jalur XLSX untuk rekonsiliasi penuh.');

            return self::SUCCESS;
        }

        if ($verifikasi->lolos($baris)) {
            $this->info('Rekonsiliasi lolos.');

            return self::SUCCESS;
        }

        $this->error('Ada selisih; periksa baris berstatus BEDA dan impor_siman2_log (status galat).');

        return self::FAILURE;
    }
}
