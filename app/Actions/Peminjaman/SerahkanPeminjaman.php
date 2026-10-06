<?php

namespace App\Actions\Peminjaman;

use App\Enums\JenisPeminjam;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\User;
use App\Support\LockAset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-09: serah terima `disetujui` → `dipinjam`. Kondisi saat pinjam dicatat ulang dari kondisi aset saat ini.
 * Permohonan pihak luar tidak dapat diserahkan lewat sistem (BR-07).
 */
class SerahkanPeminjaman
{
    public function handle(Peminjaman $peminjaman, User $pelaku): Peminjaman
    {
        Gate::forUser($pelaku)->authorize('putuskan', $peminjaman);

        if ($peminjaman->jenis_peminjam === JenisPeminjam::PihakLuar) {
            throw ValidationException::withMessages(['status' => 'Permohonan pihak luar tidak diproses sebagai peminjaman; serah terima di luar sistem ini.']);
        }

        $ids = $peminjaman->item()->pluck('aset_id')->all();

        return LockAset::dengan('pinjam', $ids, fn (): Peminjaman => DB::transaction(function () use ($peminjaman, $pelaku, $ids): Peminjaman {
            /** @var Peminjaman $terkunci */
            $terkunci = Peminjaman::query()->lockForUpdate()->findOrFail($peminjaman->getKey());

            if ($terkunci->status !== StatusPeminjaman::Disetujui) {
                throw Konsep::sudahDiputuskan($terkunci, 'diserahkan');
            }

            $cek = app(CekKetersediaan::class);
            $masalah = [];

            foreach (Aset::query()->whereIn('id', $ids)->lockForUpdate()->orderBy('id')->get() as $aset) {
                if (($alasan = $cek->alasan($aset, $terkunci->mulai->isFuture() ? $terkunci->mulai : now(), $terkunci->rencana_kembali->isFuture() ? $terkunci->rencana_kembali : now()->addMinute(), $terkunci->getKey())) !== null) {
                    $masalah[] = $alasan;

                    continue;
                }

                $terkunci->item()->where('aset_id', $aset->getKey())->update(['kondisi_saat_pinjam' => $aset->kondisi->value]);
            }

            if ($masalah !== []) {
                throw ValidationException::withMessages(['aset' => $masalah]);
            }

            $terkunci->update([
                'status' => StatusPeminjaman::Dipinjam,
                'diserahkan_oleh' => $pelaku->getKey(),
                'diserahkan_pada' => now(),
            ]);

            $peminjaman->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        }));
    }
}
