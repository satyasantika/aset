<?php

namespace App\Actions\Inventarisasi;

use App\Contracts\PenyimpananBerkas;
use App\Enums\HasilInventarisasi as Hasil;
use App\Enums\StatusInventarisasiRuangan;
use App\Models\HasilInventarisasi;
use App\Models\InventarisasiRuangan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Barang fisik yang ada di ruangan tetapi tidak ada datanya (`berlebih`). Deskripsi wajib; foto berupa tautan (§1a). */
class CatatTemuanBerlebih
{
    public function handle(InventarisasiRuangan $inventarisasi, string $deskripsi, ?string $urlFoto, User $pelaku): HasilInventarisasi
    {
        Gate::forUser($pelaku)->authorize('pindai', $inventarisasi);

        $deskripsi = trim($deskripsi);

        if (mb_strlen($deskripsi) < 3) {
            throw ValidationException::withMessages(['deskripsi' => 'Deskripsi barang temuan wajib diisi.']);
        }

        return DB::transaction(function () use ($inventarisasi, $deskripsi, $urlFoto, $pelaku): HasilInventarisasi {
            /** @var InventarisasiRuangan $terkunci */
            $terkunci = InventarisasiRuangan::query()->lockForUpdate()->findOrFail($inventarisasi->getKey());

            if ($terkunci->status === StatusInventarisasiRuangan::Selesai) {
                throw ValidationException::withMessages(['status' => 'Inventarisasi ruangan ini sudah selesai.']);
            }

            $hasil = HasilInventarisasi::query()->create([
                'inventarisasi_ruangan_id' => $terkunci->getKey(),
                'aset_id' => null,
                'hasil' => Hasil::Berlebih,
                'deskripsi_temuan' => $deskripsi,
                'dipindai_oleh' => $pelaku->getKey(),
                'dipindai_pada' => now(),
            ]);

            if (filled($urlFoto)) {
                app(PenyimpananBerkas::class)->tambah($hasil, 'foto', 'Foto temuan', (string) $urlFoto, $pelaku);
            }

            if ($terkunci->status === StatusInventarisasiRuangan::Belum) {
                $terkunci->update(['status' => StatusInventarisasiRuangan::Berjalan]);
                $inventarisasi->setRawAttributes($terkunci->getAttributes(), true);
            }

            return $hasil;
        });
    }
}
