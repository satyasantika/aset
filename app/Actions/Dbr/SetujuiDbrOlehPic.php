<?php

namespace App\Actions\Dbr;

use App\Enums\StatusDbr;
use App\Models\DbrVersi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Persetujuan (tanda tangan) PIC ruangan: `draf` → `disetujui_pic`. Isi daftar dibekukan dari data saat ini sehingga
 * yang disetujui PIC adalah yang kelak disahkan pejabat. Nama & NIP PIC dicatat pada snapshot.
 */
class SetujuiDbrOlehPic
{
    public function handle(DbrVersi $dbr, User $pelaku): DbrVersi
    {
        Gate::forUser($pelaku)->authorize('setujuiPic', $dbr);

        return DB::transaction(function () use ($dbr, $pelaku): DbrVersi {
            /** @var DbrVersi $terkunci */
            $terkunci = DbrVersi::query()->lockForUpdate()->findOrFail($dbr->getKey());

            if ($terkunci->status !== StatusDbr::Draf) {
                throw ValidationException::withMessages(['status' => "DBR berstatus {$terkunci->status->label()}; hanya draf yang dapat disetujui PIC."]);
            }

            $ruangan = $terkunci->ruangan_id ? $terkunci->ruangan : null;
            $snapshot = app(SnapshotDbr::class)->susun($ruangan, $terkunci->versi, $pelaku);
            $snapshot['dibangkitkan_pada'] = $terkunci->snapshot['dibangkitkan_pada'] ?? $snapshot['dibangkitkan_pada'];
            $snapshot['dibangkitkan_oleh'] = $terkunci->snapshot['dibangkitkan_oleh'] ?? $snapshot['dibangkitkan_oleh'];
            $snapshot['penandatangan']['pic'] = ['id' => $pelaku->getKey(), 'nama' => $pelaku->name, 'nip' => $pelaku->nip, 'pada' => now()->toIso8601String()];

            $terkunci->update([
                'status' => StatusDbr::DisetujuiPic,
                'snapshot' => $snapshot,
                'disetujui_pic_oleh' => $pelaku->getKey(),
                'disetujui_pic_pada' => now(),
            ]);

            $dbr->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
