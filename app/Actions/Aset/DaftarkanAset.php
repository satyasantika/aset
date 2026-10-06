<?php

namespace App\Actions\Aset;

use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Models\Aset;
use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Mendaftarkan satu barang fisik. Pelaku = pengguna terautentikasi (BR-21); toggle fitur dibaca server (BR-22). */
class DaftarkanAset
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $pelaku): Aset
    {
        Gate::forUser($pelaku)->authorize('create', Aset::class);
        Pengaturan::pastikanFitur('tambah_aset');

        return DB::transaction(function () use ($data, $pelaku): Aset {
            $aset = Aset::query()->create($this->bersihkan($data));

            self::tulisRiwayatAwal($aset, $pelaku);

            return $aset;
        });
    }

    /**
     * Hanya kolom yang boleh diisi saat pendaftaran; kondisi awal dan status awal tetap dari input yang sah.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function bersihkan(array $data): array
    {
        return array_diff_key($data, array_flip([
            'id', 'status', 'dicetak_pada', 'label_perlu_cetak_ulang', 'nomor_sk_penghapusan', 'tanggal_sk_penghapusan',
        ]));
    }

    public static function tulisRiwayatAwal(Aset $aset, User $pelaku): void
    {
        $aset->riwayatKondisi()->create([
            'dari' => null, 'ke' => ($aset->kondisi ?? KondisiAset::Baik)->value, 'sumber' => 'manual',
            'catatan' => 'Pendaftaran aset', 'oleh' => $pelaku->getKey(),
        ]);
        $aset->riwayatLokasi()->create([
            'dari_ruangan_id' => null, 'ke_ruangan_id' => $aset->ruangan_id, 'sumber' => 'koreksi', 'oleh' => $pelaku->getKey(),
        ]);
        $aset->riwayatStatus()->create([
            'dari' => null, 'ke' => ($aset->status ?? StatusAset::Aktif)->value,
            'catatan' => 'Pendaftaran aset', 'oleh' => $pelaku->getKey(),
        ]);
    }
}
