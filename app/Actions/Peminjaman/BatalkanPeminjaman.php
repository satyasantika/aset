<?php

namespace App\Actions\Peminjaman;

use App\Enums\StatusPeminjaman;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** BR-09: dibatalkan hanya sebelum dipinjam (`diajukan` atau `disetujui`), oleh peminjam atau pengurusnya. */
class BatalkanPeminjaman
{
    public function handle(Peminjaman $peminjaman, User $pelaku): Peminjaman
    {
        Gate::forUser($pelaku)->authorize('batalkan', $peminjaman);

        return DB::transaction(function () use ($peminjaman): Peminjaman {
            /** @var Peminjaman $terkunci */
            $terkunci = Peminjaman::query()->lockForUpdate()->findOrFail($peminjaman->getKey());

            if (! in_array($terkunci->status, [StatusPeminjaman::Diajukan, StatusPeminjaman::Disetujui], true)) {
                throw Konsep::sudahDiputuskan($terkunci, 'dibatalkan');
            }

            $terkunci->update(['status' => StatusPeminjaman::Dibatalkan]);
            $peminjaman->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
