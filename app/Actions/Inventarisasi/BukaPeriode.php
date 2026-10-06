<?php

namespace App\Actions\Inventarisasi;

use App\Enums\StatusPeriodeInventarisasi;
use App\Models\PeriodeInventarisasi;
use App\Models\User;
use App\Support\LockAset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-14: hanya SATU periode `berjalan` dalam satu waktu (lock Redis + pemeriksaan dalam transaksi). `rencana` → `berjalan`.
 */
class BukaPeriode
{
    public function handle(PeriodeInventarisasi $periode, User $pelaku): PeriodeInventarisasi
    {
        Gate::forUser($pelaku)->authorize('buka', $periode);

        return LockAset::satu('aset:inventarisasi:buka', fn (): PeriodeInventarisasi => DB::transaction(function () use ($periode): PeriodeInventarisasi {
            /** @var PeriodeInventarisasi $terkunci */
            $terkunci = PeriodeInventarisasi::query()->lockForUpdate()->findOrFail($periode->getKey());

            if ($terkunci->status !== StatusPeriodeInventarisasi::Rencana) {
                throw ValidationException::withMessages(['status' => "Periode berstatus {$terkunci->status->label()}; hanya yang berstatus rencana yang dapat dibuka."]);
            }

            $lain = PeriodeInventarisasi::query()->berjalan()->whereKeyNot($terkunci->getKey())->first();

            if ($lain !== null) {
                throw ValidationException::withMessages(['status' => "Periode \"{$lain->nama}\" masih berjalan; tutup terlebih dahulu (hanya satu periode berjalan)."]);
            }

            if ($terkunci->ruangan()->doesntExist()) {
                throw ValidationException::withMessages(['ruangan' => 'Periode belum memiliki ruangan.']);
            }

            $terkunci->update(['status' => StatusPeriodeInventarisasi::Berjalan, 'dibuka_pada' => now()]);
            $periode->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        }));
    }
}
