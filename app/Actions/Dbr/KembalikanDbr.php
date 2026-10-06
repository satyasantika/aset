<?php

namespace App\Actions\Dbr;

use App\Enums\StatusDbr;
use App\Models\DbrVersi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** `disetujui_pic` → `draf` (dikembalikan untuk diperbaiki). Tanda tangan PIC dihapus dari snapshot; catatan wajib. */
class KembalikanDbr
{
    public function handle(DbrVersi $dbr, User $pelaku, string $catatan): DbrVersi
    {
        Gate::forUser($pelaku)->authorize('kembalikan', $dbr);

        if (trim($catatan) === '') {
            throw ValidationException::withMessages(['catatan' => 'Alasan pengembalian wajib diisi.']);
        }

        return DB::transaction(function () use ($dbr, $catatan): DbrVersi {
            /** @var DbrVersi $terkunci */
            $terkunci = DbrVersi::query()->lockForUpdate()->findOrFail($dbr->getKey());

            if ($terkunci->status !== StatusDbr::DisetujuiPic) {
                throw ValidationException::withMessages(['status' => 'Hanya DBR yang menunggu pengesahan yang dapat dikembalikan.']);
            }

            $snapshot = $terkunci->snapshot;
            $snapshot['penandatangan']['pic'] = null;

            $terkunci->update([
                'status' => StatusDbr::Draf, 'snapshot' => $snapshot, 'catatan' => trim($catatan),
                'disetujui_pic_oleh' => null, 'disetujui_pic_pada' => null,
            ]);

            $dbr->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
