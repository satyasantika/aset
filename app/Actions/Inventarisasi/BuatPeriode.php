<?php

namespace App\Actions\Inventarisasi;

use App\Enums\JenisInventarisasi;
use App\Enums\StatusPeriodeInventarisasi;
use App\Models\InventarisasiRuangan;
use App\Models\PeriodeInventarisasi;
use App\Models\Ruangan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Membuat periode inventarisasi berstatus `rencana` beserta cakupan ruangannya (US-INV-01). */
class BuatPeriode
{
    /**
     * @param  array<int, string>  $idRuangan
     */
    public function handle(string $nama, JenisInventarisasi $jenis, CarbonInterface $mulai, ?CarbonInterface $selesaiRencana, array $idRuangan, User $pelaku): PeriodeInventarisasi
    {
        Gate::forUser($pelaku)->authorize('create', PeriodeInventarisasi::class);

        $ruangan = Ruangan::query()->whereIn('id', array_unique($idRuangan))->pluck('id');

        $galat = [];

        if (trim($nama) === '') {
            $galat['nama'] = 'Nama periode wajib diisi.';
        }

        if ($ruangan->isEmpty()) {
            $galat['ruangan'] = 'Pilih minimal satu ruangan.';
        }

        if ($selesaiRencana !== null && $selesaiRencana->lessThan($mulai)) {
            $galat['selesai_rencana'] = 'Tanggal selesai rencana tidak boleh sebelum tanggal mulai.';
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        return DB::transaction(function () use ($nama, $jenis, $mulai, $selesaiRencana, $ruangan): PeriodeInventarisasi {
            $periode = PeriodeInventarisasi::query()->create([
                'nama' => trim($nama), 'jenis' => $jenis, 'mulai' => $mulai, 'selesai_rencana' => $selesaiRencana,
                'status' => StatusPeriodeInventarisasi::Rencana,
            ]);

            foreach ($ruangan as $id) {
                InventarisasiRuangan::query()->create(['periode_id' => $periode->getKey(), 'ruangan_id' => $id]);
            }

            return $periode;
        });
    }
}
