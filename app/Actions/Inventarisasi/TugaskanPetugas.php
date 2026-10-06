<?php

namespace App\Actions\Inventarisasi;

use App\Enums\StatusInventarisasiRuangan;
use App\Models\InventarisasiRuangan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** US-INV-01: menugaskan tim (petugas) per ruangan. Petugas harus berhak memindai (admin atau PIC) dan akun aktif. */
class TugaskanPetugas
{
    /**
     * @param  array<int, string>  $idPetugas
     */
    public function handle(InventarisasiRuangan $inventarisasi, array $idPetugas, User $pelaku): InventarisasiRuangan
    {
        Gate::forUser($pelaku)->authorize('tugaskan', $inventarisasi);

        if ($inventarisasi->status === StatusInventarisasiRuangan::Selesai) {
            throw ValidationException::withMessages(['petugas' => 'Inventarisasi ruangan ini sudah selesai; petugas tidak dapat diubah.']);
        }

        $petugas = User::query()->where('aktif', true)->whereIn('id', array_unique($idPetugas))->get();

        if ($petugas->count() !== count(array_unique($idPetugas))) {
            throw ValidationException::withMessages(['petugas' => 'Ada akun petugas yang tidak ditemukan atau nonaktif.']);
        }

        $tidakBerhak = $petugas->filter(fn (User $u) => ! $u->can('inventarisasi.pindai'));

        if ($tidakBerhak->isNotEmpty()) {
            throw ValidationException::withMessages(['petugas' => 'Petugas harus ber-peran admin-bmn atau pic-ruangan: '.$tidakBerhak->pluck('name')->implode(', ').'.']);
        }

        DB::transaction(function () use ($inventarisasi, $petugas): void {
            $inventarisasi->petugas()->sync($petugas->pluck('id')->all());
            $inventarisasi->update(['petugas_id' => $petugas->first()?->getKey()]);
        });

        return $inventarisasi->refresh();
    }
}
