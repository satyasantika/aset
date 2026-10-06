<?php

namespace App\Actions\Aset;

use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Models\Aset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** BR-04: hanya transisi sah diagram status PRD §7; `dihapus` wajib nomor & tanggal SK; selalu menulis riwayat status. */
class UbahStatusAset
{
    /**
     * @param  array{nomor_sk_penghapusan?: string|null, tanggal_sk_penghapusan?: string|null}  $sk
     * @param  bool  $otorisasi  false hanya untuk Action alur lain (tiket, inventarisasi, penghapusan) yang sudah mengotorisasi.
     */
    public function handle(Aset $aset, StatusAset $ke, User $pelaku, ?string $catatan = null, array $sk = [], bool $otorisasi = true): Aset
    {
        if ($otorisasi) {
            Gate::forUser($pelaku)->authorize('ubahStatus', $aset);
        }

        return DB::transaction(function () use ($aset, $ke, $pelaku, $catatan, $sk): Aset {
            /** @var Aset $terkunci */
            $terkunci = Aset::query()->lockForUpdate()->findOrFail($aset->getKey());
            $dari = $terkunci->status;

            if (! $dari->dapatBerpindahKe($ke)) {
                throw ValidationException::withMessages([
                    'status' => "Transisi status {$dari->label()} → {$ke->label()} tidak sah.",
                ]);
            }

            if ($ke === StatusAset::DiusulkanHapus && $dari === StatusAset::Aktif && $terkunci->kondisi !== KondisiAset::RusakBerat) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya aset berkondisi Rusak Berat atau berstatus Hilang yang dapat diusulkan hapus (BR-16).',
                ]);
            }

            $terkunci->fill(['status' => $ke]);

            if ($ke === StatusAset::Dihapus) {
                $terkunci->fill([
                    'nomor_sk_penghapusan' => $sk['nomor_sk_penghapusan'] ?? $terkunci->nomor_sk_penghapusan,
                    'tanggal_sk_penghapusan' => $sk['tanggal_sk_penghapusan'] ?? $terkunci->tanggal_sk_penghapusan,
                ]);
            }

            $terkunci->save(); // invarian model menolak `dihapus` tanpa SK

            $terkunci->riwayatStatus()->create([
                'dari' => $dari->value, 'ke' => $ke->value, 'catatan' => $catatan, 'oleh' => $pelaku->getKey(),
            ]);

            $aset->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
