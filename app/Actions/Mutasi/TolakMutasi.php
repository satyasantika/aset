<?php

namespace App\Actions\Mutasi;

use App\Enums\StatusMutasi;
use App\Models\Mutasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Menolak pengajuan mutasi; catatan keputusan wajib. Tidak mengubah aset. */
class TolakMutasi
{
    public function handle(Mutasi $mutasi, User $pelaku, string $catatan): Mutasi
    {
        Gate::forUser($pelaku)->authorize('putuskan', $mutasi);

        if (trim($catatan) === '') {
            throw ValidationException::withMessages(['catatan_keputusan' => 'Catatan penolakan wajib diisi.']);
        }

        return DB::transaction(function () use ($mutasi, $pelaku, $catatan): Mutasi {
            /** @var Mutasi $terkunci */
            $terkunci = Mutasi::query()->lockForUpdate()->findOrFail($mutasi->getKey());

            if ($terkunci->status !== StatusMutasi::Diajukan) {
                throw ValidationException::withMessages(['status' => "Mutasi {$terkunci->nomor} sudah diputuskan ({$terkunci->status->label()})."]);
            }

            $terkunci->update([
                'status' => StatusMutasi::Ditolak,
                'diputuskan_oleh' => $pelaku->getKey(),
                'diputuskan_pada' => now(),
                'catatan_keputusan' => trim($catatan),
            ]);

            $mutasi->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
