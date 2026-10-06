<?php

namespace App\Actions\Dbr;

use App\Enums\StatusDbr;
use App\Models\DbrVersi;
use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Pengesahan pejabat-penatausahaan: `disetujui_pic` → `disahkan` (BR-13). Snapshot dilengkapi penandatangan dan waktu,
 * dan kop dari pengaturan saat itu. Ditolak bila isi aset sudah berubah sejak disetujui PIC (hash daftar berbeda).
 */
class SahkanDbr
{
    public function handle(DbrVersi $dbr, User $pelaku): DbrVersi
    {
        Gate::forUser($pelaku)->authorize('sahkan', $dbr);

        return DB::transaction(function () use ($dbr, $pelaku): DbrVersi {
            /** @var DbrVersi $terkunci */
            $terkunci = DbrVersi::query()->lockForUpdate()->findOrFail($dbr->getKey());

            if ($terkunci->status !== StatusDbr::DisetujuiPic) {
                throw ValidationException::withMessages(['status' => "DBR berstatus {$terkunci->status->label()}; hanya yang sudah disetujui PIC yang dapat disahkan."]);
            }

            $ruangan = $terkunci->ruangan_id ? $terkunci->ruangan : null;
            $sekarang = app(SnapshotDbr::class)->susun($ruangan, $terkunci->versi, $pelaku);
            $snapshot = $terkunci->snapshot;

            if (($snapshot['hash_aset'] ?? null) !== $sekarang['hash_aset']) {
                throw ValidationException::withMessages(['status' => 'Isi aset ruangan berubah sejak disetujui PIC. Kembalikan ke draf dan bangkitkan ulang sebelum disahkan.']);
            }

            $snapshot['kop'] = $sekarang['kop'];
            $snapshot['penandatangan']['pejabat'] = [
                'id' => $pelaku->getKey(),
                'nama' => $pelaku->name,
                'nip' => $pelaku->nip ?: (string) Pengaturan::ambil('penandatangan_nip'),
                'jabatan' => (string) Pengaturan::ambil('penandatangan_jabatan'),
                'pada' => now()->toIso8601String(),
            ];

            $terkunci->update([
                'status' => StatusDbr::Disahkan,
                'snapshot' => $snapshot,
                'disahkan_oleh' => $pelaku->getKey(),
                'disahkan_pada' => now(),
            ]);

            $dbr->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
