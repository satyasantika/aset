<?php

namespace App\Actions\Mutasi;

use App\Actions\Dbr\TandaiDbrPerluDiperbarui;
use App\Enums\StatusAset;
use App\Enums\StatusMutasi;
use App\Models\Aset;
use App\Models\Mutasi;
use App\Models\User;
use App\Support\LockAset;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-06: dalam SATU transaksi — `ruangan_id` setiap aset berubah, riwayat lokasi tercatat, DBR ruangan asal & tujuan
 * ditandai `perlu_diperbarui`. Aman terhadap persetujuan bersamaan: lock `aset:mutasi:{id}` + `lockForUpdate`, dan
 * status mutasi dibaca ulang di dalam lock sehingga hanya satu persetujuan yang berlaku.
 */
class SetujuiMutasi
{
    public function handle(Mutasi $mutasi, User $pelaku, ?string $catatan = null): Mutasi
    {
        Gate::forUser($pelaku)->authorize('putuskan', $mutasi);
        Pengaturan::pastikanFitur('mutasi');
        $mutasi->loadMissing(['asal', 'tujuan']);
        AjukanMutasi::pastikanTidakDiinventarisasi($mutasi->asal, $mutasi->tujuan);

        $idAset = $mutasi->aset()->pluck('aset.id')->sort()->values()->all();

        return LockAset::dengan('mutasi', $idAset, fn (): Mutasi => DB::transaction(fn (): Mutasi => $this->setujui($mutasi, $pelaku, $catatan, $idAset)));
    }

    /** @param  list<string>  $idAset */
    private function setujui(Mutasi $mutasi, User $pelaku, ?string $catatan, array $idAset): Mutasi
    {
        /** @var Mutasi $terkunci */
        $terkunci = Mutasi::query()->lockForUpdate()->findOrFail($mutasi->getKey());

        if ($terkunci->status !== StatusMutasi::Diajukan) {
            throw ValidationException::withMessages(['status' => "Mutasi {$terkunci->nomor} sudah diputuskan ({$terkunci->status->label()})."]);
        }

        $daftar = Aset::query()->whereIn('id', $idAset)->lockForUpdate()->orderBy('id')->get();

        foreach ($daftar as $aset) {
            $this->pastikanBolehPindah($aset, $terkunci);
        }

        foreach ($daftar as $aset) {
            $aset->update(['ruangan_id' => $terkunci->ruangan_tujuan_id, 'lokasi_lainnya' => null]);

            $aset->riwayatLokasi()->create([
                'dari_ruangan_id' => $terkunci->ruangan_asal_id,
                'ke_ruangan_id' => $terkunci->ruangan_tujuan_id,
                'sumber' => 'mutasi',
                'mutasi_id' => $terkunci->getKey(),
                'oleh' => $pelaku->getKey(),
            ]);
        }

        $terkunci->update([
            'status' => StatusMutasi::Disetujui,
            'diputuskan_oleh' => $pelaku->getKey(),
            'diputuskan_pada' => now(),
            'catatan_keputusan' => $catatan,
        ]);

        $dbr = app(TandaiDbrPerluDiperbarui::class);
        $dbr->handle($terkunci->ruangan_asal_id);
        $dbr->handle($terkunci->ruangan_tujuan_id);

        $mutasi->setRawAttributes($terkunci->getAttributes(), true);

        return $terkunci;
    }

    private function pastikanBolehPindah(Aset $aset, Mutasi $mutasi): void
    {
        $alasan = match (true) {
            $aset->ruangan_id !== $mutasi->ruangan_asal_id => 'sudah tidak berada di ruangan asal',
            $aset->status !== StatusAset::Aktif => "berstatus {$aset->status->label()}",
            $aset->sedangDipinjam => 'sedang dipinjam',
            default => null,
        };

        if ($alasan !== null) {
            throw ValidationException::withMessages(['aset' => "{$aset->nama} ({$aset->kode_tampil}) {$alasan}; mutasi tidak dapat disetujui."]);
        }
    }
}
