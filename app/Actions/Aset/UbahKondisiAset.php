<?php

namespace App\Actions\Aset;

use App\Enums\KondisiAset;
use App\Models\Aset;
use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * BR-03: setiap perubahan kondisi menulis `riwayat_kondisi_aset` (dari, ke, sumber, oleh, catatan).
 * BR-05: PIC hanya untuk ruangannya (Policy). BR-22: toggle fitur diperiksa untuk perubahan manual.
 */
class UbahKondisiAset
{
    public const SUMBER = ['manual', 'peminjaman', 'inventarisasi', 'laporan_kerusakan', 'migrasi'];

    public function handle(
        Aset $aset,
        KondisiAset $ke,
        User $pelaku,
        string $sumber = 'manual',
        ?string $sumberId = null,
        ?string $catatan = null,
    ): Aset {
        Gate::forUser($pelaku)->authorize('ubahKondisi', $aset);

        if ($sumber === 'manual') {
            Pengaturan::pastikanFitur('ubah_kondisi');
        }

        return DB::transaction(function () use ($aset, $ke, $pelaku, $sumber, $sumberId, $catatan): Aset {
            /** @var Aset $terkunci */
            $terkunci = Aset::query()->lockForUpdate()->findOrFail($aset->getKey());

            if ($terkunci->kondisi === $ke) {
                return $terkunci;
            }

            $dari = $terkunci->kondisi;
            $terkunci->update(['kondisi' => $ke]);

            $terkunci->riwayatKondisi()->create([
                'dari' => $dari->value,
                'ke' => $ke->value,
                'sumber' => in_array($sumber, self::SUMBER, true) ? $sumber : 'manual',
                'sumber_id' => $sumberId,
                'catatan' => $catatan,
                'oleh' => $pelaku->getKey(),
            ]);

            $aset->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
