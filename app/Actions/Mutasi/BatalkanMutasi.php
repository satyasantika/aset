<?php

namespace App\Actions\Mutasi;

use App\Enums\StatusMutasi;
use App\Models\Mutasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Pengaju (atau admin) membatalkan pengajuan yang belum diputuskan. */
class BatalkanMutasi
{
    public function handle(Mutasi $mutasi, User $pelaku): Mutasi
    {
        Gate::forUser($pelaku)->authorize('batalkan', $mutasi);

        return DB::transaction(function () use ($mutasi): Mutasi {
            /** @var Mutasi $terkunci */
            $terkunci = Mutasi::query()->lockForUpdate()->findOrFail($mutasi->getKey());

            if ($terkunci->status !== StatusMutasi::Diajukan) {
                throw ValidationException::withMessages(['status' => "Mutasi {$terkunci->nomor} sudah diputuskan ({$terkunci->status->label()})."]);
            }

            $terkunci->update(['status' => StatusMutasi::Dibatalkan]);
            $mutasi->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
