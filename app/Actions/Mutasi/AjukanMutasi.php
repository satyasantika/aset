<?php

namespace App\Actions\Mutasi;

use App\Enums\StatusAset;
use App\Enums\StatusMutasi;
use App\Models\Aset;
use App\Models\Mutasi;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\InventarisasiBerjalan;
use App\Support\NomorTransaksi;
use App\Support\Pengaturan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-06: satu/lebih aset dari SATU ruangan asal ke SATU ruangan tujuan, alasan wajib. PIC hanya dari ruangan asal
 * yang ditugaskan (BR-05). Aset yang sedang dipinjam atau tidak aktif (perbaikan, diusulkan hapus, hilang, dihapus)
 * tidak dapat dimutasi. Identitas aset tidak pernah dinomori ulang (R-17).
 */
class AjukanMutasi
{
    /**
     * @param  array<int, string>|Collection<int, string>  $asetIds
     */
    public function handle(Ruangan $asal, Ruangan $tujuan, array|Collection $asetIds, string $alasan, User $pelaku): Mutasi
    {
        Gate::forUser($pelaku)->authorize('ajukan', [Mutasi::class, $asal]);
        Pengaturan::pastikanFitur('mutasi');
        self::pastikanTidakDiinventarisasi($asal, $tujuan);

        $ids = collect($asetIds)->unique()->values();

        $this->validasi($asal, $tujuan, $ids, $alasan);

        return DB::transaction(function () use ($asal, $tujuan, $ids, $alasan, $pelaku): Mutasi {
            return NomorTransaksi::buat('MUT', Mutasi::class, 4, function (string $nomor) use ($asal, $tujuan, $ids, $alasan, $pelaku): Mutasi {
                $mutasi = Mutasi::query()->create([
                    'nomor' => $nomor,
                    'ruangan_asal_id' => $asal->getKey(),
                    'ruangan_tujuan_id' => $tujuan->getKey(),
                    'alasan' => trim($alasan),
                    'status' => StatusMutasi::Diajukan,
                    'diajukan_oleh' => $pelaku->getKey(),
                ]);

                $mutasi->aset()->attach($ids->all());

                return $mutasi;
            });
        });
    }

    /** BR-14: mutasi pada ruangan yang sedang diinventarisasi ditahan (mengikuti toggle). */
    public static function pastikanTidakDiinventarisasi(Ruangan ...$ruangan): void
    {
        if (! Pengaturan::fitur('tahan_mutasi_saat_inventarisasi')) {
            return;
        }

        foreach ($ruangan as $r) {
            if (($inv = InventarisasiBerjalan::untukRuangan($r->getKey())) !== null) {
                throw ValidationException::withMessages([
                    'aset' => "Ruangan {$r->nama} sedang diinventarisasi (periode \"{$inv->periode->nama}\"); mutasi ditahan sampai inventarisasi ruangan selesai.",
                ]);
            }
        }
    }

    /** @param  Collection<int, string>  $ids */
    private function validasi(Ruangan $asal, Ruangan $tujuan, Collection $ids, string $alasan): void
    {
        $galat = [];

        if (trim($alasan) === '') {
            $galat['alasan'] = 'Alasan mutasi wajib diisi.';
        }

        if ($asal->getKey() === $tujuan->getKey()) {
            $galat['ruangan_tujuan_id'] = 'Ruangan tujuan harus berbeda dari ruangan asal.';
        }

        if ($ids->isEmpty()) {
            $galat['aset'] = 'Pilih minimal satu aset.';
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $aset = Aset::query()->whereIn('id', $ids)->get();
        $masalah = [];

        foreach ($ids as $id) {
            /** @var Aset|null $a */
            $a = $aset->firstWhere('id', $id);

            if ($a === null) {
                $masalah[] = "Aset {$id} tidak ditemukan.";
            } elseif ($a->ruangan_id !== $asal->getKey()) {
                $masalah[] = "{$a->nama} ({$a->kode_tampil}) tidak berada di ruangan asal.";
            } elseif ($a->status !== StatusAset::Aktif) {
                $masalah[] = "{$a->nama} ({$a->kode_tampil}) berstatus {$a->status->label()} sehingga tidak dapat dimutasi.";
            } elseif ($a->sedangDipinjam) {
                $masalah[] = "{$a->nama} ({$a->kode_tampil}) sedang dipinjam.";
            }
        }

        $menunggu = DB::table('mutasi_item')
            ->join('mutasi', 'mutasi.id', '=', 'mutasi_item.mutasi_id')
            ->whereIn('mutasi_item.aset_id', $ids)
            ->where('mutasi.status', StatusMutasi::Diajukan->value)
            ->pluck('mutasi_item.aset_id');

        foreach ($menunggu as $id) {
            $a = $aset->firstWhere('id', $id);
            $masalah[] = ($a ? "{$a->nama} ({$a->kode_tampil})" : $id).' sudah ada dalam pengajuan mutasi lain yang menunggu keputusan.';
        }

        if ($masalah !== []) {
            throw ValidationException::withMessages(['aset' => $masalah]);
        }
    }
}
