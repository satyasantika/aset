<?php

namespace App\Actions\Peminjaman;

use App\Enums\StatusPeminjaman;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Menolak pengajuan; catatan wajib. */
class TolakPeminjaman
{
    public function handle(Peminjaman $peminjaman, User $pelaku, string $catatan): Peminjaman
    {
        Gate::forUser($pelaku)->authorize('putuskan', $peminjaman);

        if (trim($catatan) === '') {
            throw ValidationException::withMessages(['catatan_keputusan' => 'Alasan penolakan wajib diisi.']);
        }

        return DB::transaction(function () use ($peminjaman, $pelaku, $catatan): Peminjaman {
            /** @var Peminjaman $terkunci */
            $terkunci = Peminjaman::query()->lockForUpdate()->findOrFail($peminjaman->getKey());

            if ($terkunci->status !== StatusPeminjaman::Diajukan) {
                throw Konsep::sudahDiputuskan($terkunci, 'ditolak');
            }

            $terkunci->update([
                'status' => StatusPeminjaman::Ditolak,
                'diputuskan_oleh' => $pelaku->getKey(),
                'diputuskan_pada' => now(),
                'catatan_keputusan' => trim($catatan),
            ]);

            $peminjaman->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
