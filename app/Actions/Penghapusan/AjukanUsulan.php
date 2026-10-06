<?php

namespace App\Actions\Penghapusan;

use App\Enums\StatusUsulanHapus;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** `draf` → `diajukan` (ke pejabat-penatausahaan). Kelayakan setiap aset dicek ulang. */
class AjukanUsulan
{
    public function handle(UsulanPenghapusan $usulan, User $pelaku): UsulanPenghapusan
    {
        Gate::forUser($pelaku)->authorize('kelola', $usulan);

        return DB::transaction(function () use ($usulan): UsulanPenghapusan {
            /** @var UsulanPenghapusan $terkunci */
            $terkunci = UsulanPenghapusan::query()->lockForUpdate()->findOrFail($usulan->getKey());

            if ($terkunci->status !== StatusUsulanHapus::Draf) {
                throw ValidationException::withMessages(['status' => "Usulan {$terkunci->nomor} berstatus {$terkunci->status->label()}; hanya draf yang dapat diajukan."]);
            }

            $masalah = $terkunci->item()->with('aset')->get()
                ->map(fn ($i) => BuatUsulanPenghapusan::alasanTidakLayak($i->aset, $terkunci->getKey()))->filter()->values()->all();

            if ($masalah !== []) {
                throw ValidationException::withMessages(['aset' => $masalah]);
            }

            $terkunci->update(['status' => StatusUsulanHapus::Diajukan]);
            $usulan->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
