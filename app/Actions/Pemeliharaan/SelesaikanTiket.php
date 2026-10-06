<?php

namespace App\Actions\Pemeliharaan;

use App\Actions\Aset\UbahKondisiAset;
use App\Actions\Aset\UbahStatusAset;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusTiket;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Menutup tiket: `selesai` atau `tidak_dapat_diperbaiki`. Mencatat tindakan dan biaya (DECIMAL, BR-19); kondisi akhir
 * aset diperbarui lewat UbahKondisiAset (sumber `laporan_kerusakan`, BR-03). Bila tidak ada tiket aktif lain untuk aset
 * tersebut, status aset `dalam_perbaikan` dikembalikan ke `aktif`.
 */
class SelesaikanTiket
{
    public function handle(
        TiketPemeliharaan $tiket,
        User $pelaku,
        string $tindakan,
        string|int|float|null $biaya = null,
        ?KondisiAset $kondisiAkhir = null,
        StatusTiket $hasil = StatusTiket::Selesai,
    ): TiketPemeliharaan {
        Gate::forUser($pelaku)->authorize('kelola', $tiket);

        if ($hasil->aktif()) {
            throw ValidationException::withMessages(['status' => 'Hasil penutupan harus selesai atau tidak dapat diperbaiki.']);
        }

        if (trim($tindakan) === '') {
            throw ValidationException::withMessages(['tindakan' => 'Tindakan yang dilakukan wajib diisi.']);
        }

        $nilaiBiaya = $this->biaya($biaya);

        return DB::transaction(function () use ($tiket, $pelaku, $tindakan, $nilaiBiaya, $kondisiAkhir, $hasil): TiketPemeliharaan {
            /** @var TiketPemeliharaan $terkunci */
            $terkunci = TiketPemeliharaan::query()->lockForUpdate()->findOrFail($tiket->getKey());

            if (! $terkunci->status->aktif()) {
                throw ValidationException::withMessages(['status' => "Tiket {$terkunci->nomor} sudah ditutup ({$terkunci->status->label()})."]);
            }

            $terkunci->update([
                'status' => $hasil,
                'tindakan' => trim($tindakan),
                'biaya' => $nilaiBiaya,
                'ditangani_oleh' => $pelaku->getKey(),
                'selesai_pada' => now(),
            ]);

            $aset = $terkunci->aset;
            $kondisi = $kondisiAkhir ?? ($hasil === StatusTiket::TidakDapatDiperbaiki ? KondisiAset::RusakBerat : null);

            if ($kondisi !== null) {
                app(UbahKondisiAset::class)->handle($aset, $kondisi, $pelaku, 'laporan_kerusakan', $terkunci->getKey(), "Penutupan tiket {$terkunci->nomor}");
            }

            $masihAdaTiketAktif = TiketPemeliharaan::query()->aktif()->where('aset_id', $aset->getKey())->whereKeyNot($terkunci->getKey())->exists();
            $aset->refresh();

            if (! $masihAdaTiketAktif && $aset->status === StatusAset::DalamPerbaikan) {
                app(UbahStatusAset::class)->handle($aset, StatusAset::Aktif, $pelaku, "Tiket {$terkunci->nomor} ditutup", [], otorisasi: false);
            }

            $tiket->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }

    private function biaya(string|int|float|null $biaya): ?string
    {
        if ($biaya === null || $biaya === '') {
            return null;
        }

        $teks = is_string($biaya) ? str_replace(',', '.', trim($biaya)) : (string) $biaya;

        if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $teks)) {
            throw ValidationException::withMessages(['biaya' => 'Biaya harus angka tidak negatif dengan maksimal dua desimal.']);
        }

        return number_format((float) $teks, 2, '.', '');
    }
}
