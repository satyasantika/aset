<?php

namespace App\Console\Commands;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Per jam: berkas keluaran sementara (ekspor, PDF, temp) di disk `tmp` yang lebih tua dari 24 jam dihapus (STANDAR-TEKNIS §1a). */
class BersihkanTmp extends Command
{
    public const MASA_JAM = 24;

    protected $signature = 'aset:bersihkan-tmp';

    protected $description = 'Hapus berkas di disk tmp yang lebih tua dari 24 jam';

    public function handle(): int
    {
        $disk = Storage::disk('tmp');
        $batas = now()->subHours(self::MASA_JAM)->getTimestamp();
        $jumlah = 0;

        foreach ($disk->allFiles() as $berkas) {
            if ($disk->lastModified($berkas) < $batas) {
                $disk->delete($berkas);
                $jumlah++;
            }
        }

        foreach ($disk->directories() as $dir) {
            if ($disk->allFiles($dir) === [] && $disk->lastModified($dir) < $batas) {
                $disk->deleteDirectory($dir);
            }
        }

        Export::query()->where('created_at', '<', now()->subHours(self::MASA_JAM))->where('file_disk', 'tmp')->delete();

        $this->info("{$jumlah} berkas dihapus dari tmp.");

        return self::SUCCESS;
    }
}
