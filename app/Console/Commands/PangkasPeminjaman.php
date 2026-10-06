<?php

namespace App\Console\Commands;

use App\Enums\StatusPeminjaman;
use App\Models\Aktivitas;
use App\Models\Peminjaman;
use App\Support\Pengaturan;
use Illuminate\Console\Command;

/**
 * Bulanan (BR-23): data pribadi peminjam pada peminjaman yang sudah selesai lebih dari `retensi_peminjaman_bulan`
 * (bawaan 36) dianonimkan — nama, kontak, unit, keperluan, dan tautan ke akun. Catatan jumlah/barang/tanggal tetap
 * dipertahankan untuk statistik; salinan data pribadi di log aktivitas ikut dibersihkan.
 */
class PangkasPeminjaman extends Command
{
    public const PENANDA = '(dianonimkan)';

    protected $signature = 'aset:pangkas-peminjaman';

    protected $description = 'Anonimkan data pribadi peminjaman yang melewati masa retensi (BR-23)';

    public function handle(): int
    {
        $bulan = max(1, (int) Pengaturan::ambil('retensi_peminjaman_bulan', 36));
        $batas = now()->subMonths($bulan);
        $jumlah = 0;

        Peminjaman::query()
            ->whereIn('status', [StatusPeminjaman::Dikembalikan->value, StatusPeminjaman::Ditolak->value, StatusPeminjaman::Dibatalkan->value])
            ->where('updated_at', '<', $batas)->where('nama_peminjam', '!=', self::PENANDA)
            ->each(function (Peminjaman $p) use (&$jumlah): void {
                $p->disableLogging();
                $p->forceFill([
                    'nama_peminjam' => self::PENANDA, 'kontak_peminjam' => null, 'unit_peminjam' => null,
                    'keperluan' => self::PENANDA, 'peminjam_user_id' => null,
                ])->saveQuietly();

                Aktivitas::query()->where('subject_type', $p->getMorphClass())->where('subject_id', $p->getKey())
                    ->update(['properties' => '[]']);

                $jumlah++;
            });

        $this->info("{$jumlah} peminjaman dianonimkan (retensi {$bulan} bulan).");

        return self::SUCCESS;
    }
}
