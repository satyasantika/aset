<?php

namespace App\Support;

use App\Enums\StatusInventarisasiRuangan;
use App\Models\InventarisasiRuangan;

/** Pertanyaan umum: apakah suatu ruangan sedang diinventarisasi (periode berjalan, ruangan belum selesai)? */
class InventarisasiBerjalan
{
    public static function untukRuangan(?string $ruanganId): ?InventarisasiRuangan
    {
        if ($ruanganId === null) {
            return null;
        }

        return InventarisasiRuangan::query()
            ->with('periode')
            ->where('ruangan_id', $ruanganId)
            ->whereIn('status', [StatusInventarisasiRuangan::Belum->value, StatusInventarisasiRuangan::Berjalan->value])
            ->whereHas('periode', fn ($q) => $q->berjalan())
            ->first();
    }
}
