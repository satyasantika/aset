<?php

namespace App\Console\Commands;

use App\Actions\Migrasi\Siman2\ImporDariSiman2;
use App\Actions\Migrasi\Siman2\PemetaanPengguna;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImporSiman2 extends Command
{
    protected $signature = 'siman2:impor
        {berkas : Jalur berkas XLSX ekspor spreadsheet SIMAN-2}
        {--dry-run : Validasi dan laporan galat tanpa menulis data}
        {--nup-berurutan : Konfirmasi NUP lama berurutan (NUP awal + i) untuk baris berjumlah > 1}
        {--pemetaan=docs/migrasi/pemetaan-pengguna.csv : CSV pemetaan username,email}';

    protected $description = 'Impor data SIMAN-FKIP-2 (Google Sheets, XLSX) ke SIMAN FKIP 3 (idempoten; lihat docs/07-MIGRASI-DATA.md)';

    public function handle(ImporDariSiman2 $impor): int
    {
        $berkas = (string) $this->argument('berkas');
        $dryRun = (bool) $this->option('dry-run');
        $pemetaan = (string) $this->option('pemetaan');

        $laporan = $impor->handle(
            $berkas,
            $dryRun,
            (bool) $this->option('nup-berurutan'),
            PemetaanPengguna::baca(str_starts_with($pemetaan, '/') ? $pemetaan : base_path($pemetaan)),
        );

        $this->info($dryRun ? 'DRY-RUN — tidak ada data yang ditulis.' : 'Impor selesai.');
        $this->table(
            ['Sheet', 'Berhasil', 'Dilewati', 'Galat'],
            collect($laporan->hitungan)->map(fn (array $h, string $sheet) => [$sheet, $h['ok'], $h['dilewati'], $h['galat']])->values()->all(),
        );

        foreach ($laporan->peringatan as $p) {
            $this->warn("[{$p['sheet']}#{$p['id']}] {$p['pesan']}");
        }

        foreach ($laporan->galat as $g) {
            $this->error("[{$g['sheet']}#{$g['id']}] {$g['pesan']}");
        }

        Log::info('siman2:impor', [
            'dry_run' => $dryRun, 'hitungan' => $laporan->hitungan, 'galat' => $laporan->galat, 'peringatan' => $laporan->peringatan,
        ]);

        if (! $dryRun && ! $laporan->adaGalat()) {
            $this->hapusBerkasSementara($berkas);
        }

        return $laporan->adaGalat() ? self::FAILURE : self::SUCCESS;
    }

    /** Berkas ekspor hanya boleh bertahan di storage/app/tmp selama impor (kebijakan §1a, tanpa unggahan permanen). */
    private function hapusBerkasSementara(string $berkas): void
    {
        $nyata = realpath($berkas);
        $tmp = realpath(storage_path('app/tmp'));

        if ($nyata !== false && $tmp !== false && str_starts_with($nyata, $tmp.DIRECTORY_SEPARATOR)) {
            @unlink($nyata);
            $this->line('Berkas sementara dihapus.');
        }
    }
}
