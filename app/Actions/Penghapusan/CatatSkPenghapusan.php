<?php

namespace App\Actions\Penghapusan;

use App\Actions\Aset\UbahStatusAset;
use App\Contracts\PenyimpananBerkas;
use App\Enums\StatusAset;
use App\Enums\StatusUsulanHapus;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * RG-09: setelah SK penghapusan terbit, setiap aset menjadi `dihapus` dengan nomor & tanggal SK (tanpa menghapus baris).
 * SK berupa tautan berkas (STANDAR-TEKNIS §1a) pada usulan.
 */
class CatatSkPenghapusan
{
    public function handle(UsulanPenghapusan $usulan, string $nomorSk, CarbonInterface $tanggalSk, ?string $urlSk, User $pelaku): UsulanPenghapusan
    {
        Gate::forUser($pelaku)->authorize('kelola', $usulan);

        $galat = [];

        if (trim($nomorSk) === '') {
            $galat['nomor_sk'] = 'Nomor SK penghapusan wajib diisi.';
        }

        if ($tanggalSk->isFuture()) {
            $galat['tanggal_sk'] = 'Tanggal SK tidak boleh di masa depan.';
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        return DB::transaction(function () use ($usulan, $nomorSk, $tanggalSk, $urlSk, $pelaku): UsulanPenghapusan {
            /** @var UsulanPenghapusan $terkunci */
            $terkunci = UsulanPenghapusan::query()->lockForUpdate()->findOrFail($usulan->getKey());

            if ($terkunci->status !== StatusUsulanHapus::DisetujuiInternal) {
                throw ValidationException::withMessages(['status' => "Usulan {$terkunci->nomor} berstatus {$terkunci->status->label()}; SK hanya dicatat setelah disetujui internal."]);
            }

            foreach ($terkunci->item()->with('aset')->get() as $item) {
                app(UbahStatusAset::class)->handle(
                    $item->aset, StatusAset::Dihapus, $pelaku, "SK penghapusan {$nomorSk} ({$terkunci->nomor})",
                    ['nomor_sk_penghapusan' => trim($nomorSk), 'tanggal_sk_penghapusan' => $tanggalSk->toDateString()], otorisasi: false,
                );
            }

            $terkunci->update(['status' => StatusUsulanHapus::SkTerbit, 'nomor_sk' => trim($nomorSk), 'tanggal_sk' => $tanggalSk]);

            if (filled($urlSk)) {
                app(PenyimpananBerkas::class)->tambah($terkunci, 'sk', "SK penghapusan {$nomorSk}", (string) $urlSk, $pelaku);
            }

            $usulan->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        });
    }
}
