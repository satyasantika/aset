<?php

namespace App\Actions\Penghapusan;

use App\Actions\Aset\UbahStatusAset;
use App\Enums\StatusAset;
use App\Enums\StatusUsulanHapus;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** BR-16: pejabat menyetujui internal → setiap aset berstatus `diusulkan_hapus` (satu transaksi). */
class SetujuiUsulan
{
    public function handle(UsulanPenghapusan $usulan, User $pejabat, ?string $catatan = null): UsulanPenghapusan
    {
        Gate::forUser($pejabat)->authorize('putuskan', $usulan);

        return DB::transaction(function () use ($usulan, $pejabat, $catatan): UsulanPenghapusan {
            /** @var UsulanPenghapusan $terkunci */
            $terkunci = UsulanPenghapusan::query()->lockForUpdate()->findOrFail($usulan->getKey());

            if ($terkunci->status !== StatusUsulanHapus::Diajukan) {
                throw ValidationException::withMessages(['status' => "Usulan {$terkunci->nomor} berstatus {$terkunci->status->label()}; hanya yang diajukan dapat diputuskan."]);
            }

            foreach ($terkunci->item()->with('aset')->get() as $item) {
                if (($alasan = BuatUsulanPenghapusan::alasanTidakLayak($item->aset, $terkunci->getKey())) !== null) {
                    throw ValidationException::withMessages(['aset' => $alasan]);
                }

                app(UbahStatusAset::class)->handle($item->aset, StatusAset::DiusulkanHapus, $pejabat, "Usulan penghapusan {$terkunci->nomor} disetujui internal", [], otorisasi: false);
            }

            $terkunci->update(['status' => StatusUsulanHapus::DisetujuiInternal, 'pemutus_id' => $pejabat->getKey(), 'diputuskan_pada' => now(), 'catatan_keputusan' => $catatan]);
            $usulan->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
