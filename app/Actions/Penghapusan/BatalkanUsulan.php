<?php

namespace App\Actions\Penghapusan;

use App\Actions\Aset\UbahStatusAset;
use App\Enums\StatusAset;
use App\Enums\StatusUsulanHapus;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use App\Models\UsulanPenghapusanItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Membatalkan usulan yang belum ber-SK. Aset `diusulkan_hapus` dipulihkan ke status semula (aktif/hilang). */
class BatalkanUsulan
{
    public function handle(UsulanPenghapusan $usulan, User $pelaku): UsulanPenghapusan
    {
        Gate::forUser($pelaku)->authorize('kelola', $usulan);

        return DB::transaction(function () use ($usulan, $pelaku): UsulanPenghapusan {
            /** @var UsulanPenghapusan $terkunci */
            $terkunci = UsulanPenghapusan::query()->lockForUpdate()->findOrFail($usulan->getKey());

            if (! $terkunci->status->terbuka()) {
                throw ValidationException::withMessages(['status' => "Usulan {$terkunci->nomor} berstatus {$terkunci->status->label()}; tidak dapat dibatalkan."]);
            }

            if ($terkunci->status === StatusUsulanHapus::DisetujuiInternal) {
                foreach ($terkunci->item()->with('aset')->get() as $item) {
                    if ($item->aset->status === StatusAset::DiusulkanHapus) {
                        $semula = $item->alasan_item === UsulanPenghapusanItem::HILANG ? StatusAset::Hilang : StatusAset::Aktif;
                        app(UbahStatusAset::class)->handle($item->aset, $semula, $pelaku, "Usulan penghapusan {$terkunci->nomor} dibatalkan", [], otorisasi: false);
                    }
                }
            }

            $terkunci->update(['status' => StatusUsulanHapus::Dibatalkan]);
            $usulan->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
