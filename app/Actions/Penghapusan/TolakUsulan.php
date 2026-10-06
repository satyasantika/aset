<?php

namespace App\Actions\Penghapusan;

use App\Enums\StatusUsulanHapus;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Pejabat mengembalikan usulan `diajukan` ke `draf` dengan catatan wajib; tidak mengubah aset. */
class TolakUsulan
{
    public function handle(UsulanPenghapusan $usulan, User $pejabat, string $catatan): UsulanPenghapusan
    {
        Gate::forUser($pejabat)->authorize('putuskan', $usulan);

        if (trim($catatan) === '') {
            throw ValidationException::withMessages(['catatan' => 'Alasan pengembalian wajib diisi.']);
        }

        return DB::transaction(function () use ($usulan, $pejabat, $catatan): UsulanPenghapusan {
            /** @var UsulanPenghapusan $terkunci */
            $terkunci = UsulanPenghapusan::query()->lockForUpdate()->findOrFail($usulan->getKey());

            if ($terkunci->status !== StatusUsulanHapus::Diajukan) {
                throw ValidationException::withMessages(['status' => "Usulan {$terkunci->nomor} berstatus {$terkunci->status->label()}; hanya yang diajukan dapat dikembalikan."]);
            }

            $terkunci->update(['status' => StatusUsulanHapus::Draf, 'pemutus_id' => $pejabat->getKey(), 'diputuskan_pada' => now(), 'catatan_keputusan' => trim($catatan)]);
            $usulan->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
