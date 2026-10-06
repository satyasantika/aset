<?php

namespace App\Actions\Dbr;

use App\Enums\StatusDbr;
use App\Models\DbrVersi;

/**
 * BR-13: versi DBR/DBL yang sudah disahkan ditandai `perlu_diperbarui` bila isi aset di ruangannya berubah (mutasi,
 * pendaftaran, perubahan kondisi/status/data). `null` = DBL (aset berlokasi lainnya). Idempoten dan ringan; dipanggil
 * dari observer model Aset serta eksplisit dari SetujuiMutasi, di dalam transaksi pemanggil.
 */
class TandaiDbrPerluDiperbarui
{
    public function handle(?string $ruanganId): void
    {
        DbrVersi::query()
            ->where('jenis', $ruanganId === null ? DbrVersi::JENIS_DBL : DbrVersi::JENIS_DBR)
            ->when($ruanganId === null, fn ($q) => $q->whereNull('ruangan_id'), fn ($q) => $q->where('ruangan_id', $ruanganId))
            ->where('status', StatusDbr::Disahkan->value)
            ->update(['status' => StatusDbr::PerluDiperbarui->value, 'updated_at' => now()]);
    }
}
