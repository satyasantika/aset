<?php

namespace App\Actions\Pemeliharaan;

use App\Enums\StatusTiket;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Transisi tiket berjalan: baru → diproses ⇄ menunggu_suku_cadang. Penyelesaian lewat SelesaikanTiket. */
class UbahStatusTiket
{
    /** @var array<string, list<StatusTiket>> */
    private const TRANSISI = [
        'baru' => [StatusTiket::Diproses],
        'diproses' => [StatusTiket::MenungguSukuCadang],
        'menunggu_suku_cadang' => [StatusTiket::Diproses],
    ];

    public function handle(TiketPemeliharaan $tiket, StatusTiket $ke, User $pelaku): TiketPemeliharaan
    {
        Gate::forUser($pelaku)->authorize('kelola', $tiket);

        return DB::transaction(function () use ($tiket, $ke, $pelaku): TiketPemeliharaan {
            /** @var TiketPemeliharaan $terkunci */
            $terkunci = TiketPemeliharaan::query()->lockForUpdate()->findOrFail($tiket->getKey());

            if (! in_array($ke, self::TRANSISI[$terkunci->status->value] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Transisi tiket {$terkunci->status->label()} → {$ke->label()} tidak sah."]);
            }

            $terkunci->update(['status' => $ke, 'ditangani_oleh' => $terkunci->ditangani_oleh ?? $pelaku->getKey()]);
            $tiket->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
