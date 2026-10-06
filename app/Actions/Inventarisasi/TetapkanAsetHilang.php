<?php

namespace App\Actions\Inventarisasi;

use App\Actions\Aset\UbahStatusAset;
use App\Enums\HasilInventarisasi as Hasil;
use App\Enums\StatusAset;
use App\Enums\StatusPeriodeInventarisasi;
use App\Models\HasilInventarisasi;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Verifikasi admin atas aset `tidak_ditemukan`: bila memang hilang → status aset `hilang` dengan catatan (BR-14).
 * Hanya setelah periode ditutup.
 */
class TetapkanAsetHilang
{
    public function handle(HasilInventarisasi $hasil, User $admin, string $catatan): void
    {
        // Selalu baca ulang: status periode/aset dapat berubah sejak $hasil dimuat.
        $hasil = HasilInventarisasi::query()->with(['aset', 'inventarisasiRuangan.periode'])->findOrFail($hasil->getKey());
        $periode = $hasil->inventarisasiRuangan->periode;
        Gate::forUser($admin)->authorize('verifikasi', $periode);

        if ($hasil->hasil !== Hasil::TidakDitemukan || $hasil->aset === null) {
            throw ValidationException::withMessages(['hasil' => 'Hanya aset berhasil tidak ditemukan yang dapat ditetapkan hilang.']);
        }

        if (! in_array($periode->status, [StatusPeriodeInventarisasi::Ditutup, StatusPeriodeInventarisasi::Disahkan], true)) {
            throw ValidationException::withMessages(['status' => 'Verifikasi dilakukan setelah periode ditutup.']);
        }

        if (trim($catatan) === '') {
            throw ValidationException::withMessages(['catatan' => 'Catatan verifikasi wajib diisi.']);
        }

        if ($hasil->aset->status === StatusAset::Hilang) {
            return; // sudah ditetapkan
        }

        app(UbahStatusAset::class)->handle($hasil->aset, StatusAset::Hilang, $admin, trim($catatan)." (inventarisasi \"{$periode->nama}\")");
    }
}
