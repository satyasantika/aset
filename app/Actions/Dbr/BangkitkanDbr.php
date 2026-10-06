<?php

namespace App\Actions\Dbr;

use App\Enums\StatusDbr;
use App\Models\DbrVersi;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * US-DBR-01: membangkitkan DBR (satu ruangan) atau DBL (`$ruangan = null`: aset berlokasi lainnya). Versi baru berisi
 * snapshot data aset saat ini. Bila sudah ada versi `draf`, snapshotnya diperbarui (bukan versi baru). Versi yang sedang
 * menunggu pengesahan (`disetujui_pic`) harus dikembalikan dulu.
 */
class BangkitkanDbr
{
    public function handle(?Ruangan $ruangan, User $pelaku): DbrVersi
    {
        Gate::forUser($pelaku)->authorize('bangkitkan', [DbrVersi::class, $ruangan]);

        $jenis = $ruangan === null ? DbrVersi::JENIS_DBL : DbrVersi::JENIS_DBR;
        $kunci = 'aset:dbr:'.($ruangan?->getKey() ?? 'dbl');

        return Cache::lock($kunci, 10)->block(5, fn (): DbrVersi => DB::transaction(function () use ($ruangan, $pelaku, $jenis): DbrVersi {
            $dasar = DbrVersi::query()->where('jenis', $jenis)
                ->when($ruangan === null, fn ($q) => $q->whereNull('ruangan_id'), fn ($q) => $q->where('ruangan_id', $ruangan?->getKey()));

            $berjalan = (clone $dasar)->whereIn('status', [StatusDbr::Draf->value, StatusDbr::DisetujuiPic->value])->orderByDesc('versi')->first();

            if ($berjalan?->status === StatusDbr::DisetujuiPic) {
                throw ValidationException::withMessages(['status' => "DBR versi {$berjalan->versi} sedang menunggu pengesahan; kembalikan ke draf bila perlu diperbarui."]);
            }

            $versi = $berjalan !== null ? $berjalan->versi : ((int) (clone $dasar)->max('versi')) + 1;
            $snapshot = app(SnapshotDbr::class)->susun($ruangan, $versi, $pelaku);

            if ($berjalan !== null) {
                $berjalan->update(['snapshot' => $snapshot]);

                return $berjalan;
            }

            return DbrVersi::query()->create([
                'ruangan_id' => $ruangan?->getKey(),
                'jenis' => $jenis,
                'versi' => $versi,
                'status' => StatusDbr::Draf,
                'snapshot' => $snapshot,
            ]);
        }));
    }
}
