<?php

namespace App\Actions\Inventarisasi;

use App\Enums\HasilInventarisasi as Hasil;
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
 * Menutup inventarisasi satu ruangan: setiap aset yang seharusnya ada (bukan hilang/dihapus) tetapi belum dipindai
 * otomatis dicatat `tidak_ditemukan` (BR-14). Idempoten per aset.
 */
class SelesaikanInventarisasiRuangan
{
    public function handle(InventarisasiRuangan $inventarisasi, User $pelaku): InventarisasiRuangan
    {
        Gate::forUser($pelaku)->authorize('pindai', $inventarisasi);

        return DB::transaction(function () use ($inventarisasi, $pelaku): InventarisasiRuangan {
            /** @var InventarisasiRuangan $terkunci */
            $terkunci = InventarisasiRuangan::query()->lockForUpdate()->findOrFail($inventarisasi->getKey());

            if ($terkunci->status === StatusInventarisasiRuangan::Selesai) {
                throw ValidationException::withMessages(['status' => 'Inventarisasi ruangan ini sudah selesai.']);
            }

            $sudah = HasilInventarisasi::query()->where('inventarisasi_ruangan_id', $terkunci->getKey())->whereNotNull('aset_id')->pluck('aset_id');

            Aset::query()->where('ruangan_id', $terkunci->ruangan_id)
                ->whereNotIn('status', [StatusAset::Hilang->value, StatusAset::Dihapus->value])
                ->whereNotIn('id', $sudah)
                ->get()
                ->each(fn (Aset $aset) => HasilInventarisasi::query()->create([
                    'inventarisasi_ruangan_id' => $terkunci->getKey(),
                    'aset_id' => $aset->getKey(),
                    'hasil' => Hasil::TidakDitemukan,
                    'dipindai_oleh' => $pelaku->getKey(),
                    'dipindai_pada' => now(),
                ]));

            $terkunci->update(['status' => StatusInventarisasiRuangan::Selesai, 'selesai_pada' => now()]);
            $inventarisasi->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }

    /** Kemajuan (persen) pemindaian: aset yang ditemukan / yang seharusnya ada. */
    public static function progres(InventarisasiRuangan $inventarisasi): int
    {
        $total = Aset::query()->where('ruangan_id', $inventarisasi->ruangan_id)
            ->whereNotIn('status', [StatusAset::Hilang->value, StatusAset::Dihapus->value])->count();

        if ($total === 0) {
            return $inventarisasi->status === StatusInventarisasiRuangan::Selesai ? 100 : 0;
        }

        $ditemukan = HasilInventarisasi::query()->where('inventarisasi_ruangan_id', $inventarisasi->getKey())
            ->whereIn('hasil', [Hasil::Ditemukan->value, Hasil::KondisiBerubah->value])->whereNotNull('aset_id')->count();

        return (int) min(100, round($ditemukan / $total * 100));
    }
}
