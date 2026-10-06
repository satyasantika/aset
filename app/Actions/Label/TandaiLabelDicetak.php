<?php

namespace App\Actions\Label;

use App\Models\Aset;
use App\Models\User;
use Illuminate\Support\Collection;

/** Mengisi `dicetak_pada` dan mengosongkan `label_perlu_cetak_ulang`; satu entri audit per cetak. */
class TandaiLabelDicetak
{
    /** @param  Collection<int, Aset>  $aset */
    public function handle(Collection $aset, User $pelaku): void
    {
        if ($aset->isEmpty()) {
            return;
        }

        $ids = $aset->pluck('id')->all();

        Aset::query()->whereIn('id', $ids)->update(['dicetak_pada' => now(), 'label_perlu_cetak_ulang' => false]);

        activity('label')
            ->causedBy($pelaku)
            ->withProperties(['jumlah' => count($ids), 'aset_id' => $ids])
            ->log('label dicetak');
    }
}
