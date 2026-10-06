<?php

namespace App\Actions\Peminjaman;

use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\User;
use App\Support\LockAset;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-09: `diajukan` → `disetujui` oleh PIC ruangan asal/admin (internal) atau pejabat-penatausahaan (pihak luar).
 * BR-08: ketersediaan dicek ulang di dalam lock per aset + transaksi; bentrok → persetujuan ditolak.
 */
class SetujuiPeminjaman
{
    public function handle(Peminjaman $peminjaman, User $pelaku, ?string $catatan = null): Peminjaman
    {
        Gate::forUser($pelaku)->authorize('putuskan', $peminjaman);
        Pengaturan::pastikanFitur('peminjaman');

        $ids = $peminjaman->item()->pluck('aset_id')->all();

        return LockAset::dengan('pinjam', $ids, fn (): Peminjaman => DB::transaction(function () use ($peminjaman, $pelaku, $catatan, $ids): Peminjaman {
            /** @var Peminjaman $terkunci */
            $terkunci = Peminjaman::query()->lockForUpdate()->findOrFail($peminjaman->getKey());

            if ($terkunci->status !== StatusPeminjaman::Diajukan) {
                throw Konsep::sudahDiputuskan($terkunci, 'disetujui');
            }

            $masalah = [];
            $cek = app(CekKetersediaan::class);

            foreach (Aset::query()->whereIn('id', $ids)->lockForUpdate()->orderBy('id')->get() as $aset) {
                if (($alasan = $cek->alasan($aset, $terkunci->mulai, $terkunci->rencana_kembali, $terkunci->getKey())) !== null) {
                    $masalah[] = $alasan;
                }
            }

            if ($masalah !== []) {
                throw ValidationException::withMessages(['aset' => $masalah]);
            }

            $terkunci->update([
                'status' => StatusPeminjaman::Disetujui,
                'diputuskan_oleh' => $pelaku->getKey(),
                'diputuskan_pada' => now(),
                'catatan_keputusan' => $catatan,
            ]);

            $peminjaman->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        }));
    }
}
