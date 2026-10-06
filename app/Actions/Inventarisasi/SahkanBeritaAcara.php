<?php

namespace App\Actions\Inventarisasi;

use App\Enums\StatusPeriodeInventarisasi;
use App\Models\PeriodeInventarisasi;
use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Pengesahan berita acara oleh pejabat-penatausahaan (`ditutup` → `disahkan`); nama/NIP/jabatan masuk snapshot (BR-14). */
class SahkanBeritaAcara
{
    public function handle(PeriodeInventarisasi $periode, User $pejabat): PeriodeInventarisasi
    {
        Gate::forUser($pejabat)->authorize('sahkan', $periode);

        return DB::transaction(function () use ($periode, $pejabat): PeriodeInventarisasi {
            /** @var PeriodeInventarisasi $terkunci */
            $terkunci = PeriodeInventarisasi::query()->lockForUpdate()->findOrFail($periode->getKey());

            if ($terkunci->status !== StatusPeriodeInventarisasi::Ditutup) {
                throw ValidationException::withMessages(['status' => "Periode berstatus {$terkunci->status->label()}; hanya periode ditutup yang dapat disahkan."]);
            }

            $berita = $terkunci->berita_acara ?? [];
            $berita['penandatangan']['pejabat'] = [
                'id' => $pejabat->getKey(), 'nama' => $pejabat->name,
                'nip' => $pejabat->nip ?: (string) Pengaturan::ambil('penandatangan_nip'),
                'jabatan' => (string) Pengaturan::ambil('penandatangan_jabatan'),
                'pada' => now()->toIso8601String(),
            ];

            $terkunci->update(['status' => StatusPeriodeInventarisasi::Disahkan, 'berita_acara' => $berita, 'disahkan_oleh' => $pejabat->getKey(), 'disahkan_pada' => now()]);
            $periode->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
