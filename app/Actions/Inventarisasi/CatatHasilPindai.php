<?php

namespace App\Actions\Inventarisasi;

use App\Enums\HasilInventarisasi as Hasil;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusInventarisasiRuangan;
use App\Models\Aset;
use App\Models\HasilInventarisasi;
use App\Models\InventarisasiRuangan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-14: mencatat bahwa satu aset DITEMUKAN pada ruangan yang sedang diinventarisasi. Kondisi yang ditemukan berbeda
 * dari data → `kondisi_berubah` (kondisi aset baru diterapkan saat periode ditutup, F9.4). Idempoten per aset: memindai
 * ulang memperbarui hasil. Aset milik ruangan lain ditolak dengan pesan jelas (bukan dicatat di ruangan ini).
 */
class CatatHasilPindai
{
    public function handle(InventarisasiRuangan $inventarisasi, Aset $aset, User $pelaku, ?KondisiAset $kondisiDitemukan = null): HasilInventarisasi
    {
        Gate::forUser($pelaku)->authorize('pindai', $inventarisasi);

        if ($aset->status === StatusAset::Dihapus) {
            throw ValidationException::withMessages(['aset' => "{$aset->nama} sudah dihapus dari daftar aset."]);
        }

        if ($aset->ruangan_id !== $inventarisasi->ruangan_id) {
            $asal = $aset->ruangan !== null ? $aset->ruangan->nama : ($aset->lokasi_lainnya ?: 'lokasi lain');
            throw ValidationException::withMessages(['aset' => "{$aset->nama} ({$aset->kode_tampil}) tercatat di {$asal}, bukan di ruangan ini. Ajukan mutasi bila memang dipindahkan, atau catat sebagai temuan berlebih."]);
        }

        return DB::transaction(function () use ($inventarisasi, $aset, $pelaku, $kondisiDitemukan): HasilInventarisasi {
            /** @var InventarisasiRuangan $terkunci */
            $terkunci = InventarisasiRuangan::query()->lockForUpdate()->findOrFail($inventarisasi->getKey());

            if ($terkunci->status === StatusInventarisasiRuangan::Selesai) {
                throw ValidationException::withMessages(['status' => 'Inventarisasi ruangan ini sudah selesai.']);
            }

            $berubah = $kondisiDitemukan !== null && $kondisiDitemukan !== $aset->kondisi;

            $hasil = HasilInventarisasi::query()->updateOrCreate(
                ['inventarisasi_ruangan_id' => $terkunci->getKey(), 'aset_id' => $aset->getKey()],
                [
                    'hasil' => $berubah ? Hasil::KondisiBerubah : Hasil::Ditemukan,
                    'kondisi_ditemukan' => $berubah ? $kondisiDitemukan : null,
                    'deskripsi_temuan' => null,
                    'dipindai_oleh' => $pelaku->getKey(),
                    'dipindai_pada' => now(),
                ],
            );

            if ($terkunci->status === StatusInventarisasiRuangan::Belum) {
                $terkunci->update(['status' => StatusInventarisasiRuangan::Berjalan]);
                $inventarisasi->setRawAttributes($terkunci->getAttributes(), true);
            }

            return $hasil;
        });
    }
}
