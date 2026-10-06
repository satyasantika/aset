<?php

namespace App\Actions\Inventarisasi;

use App\Actions\Aset\UbahKondisiAset;
use App\Actions\Aset\UbahStatusAset;
use App\Enums\HasilInventarisasi as Hasil;
use App\Enums\StatusAset;
use App\Enums\StatusInventarisasiRuangan;
use App\Enums\StatusPeriodeInventarisasi;
use App\Models\HasilInventarisasi;
use App\Models\PeriodeInventarisasi;
use App\Models\User;
use App\Support\LockAset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-14: menutup periode (`berjalan` → `ditutup`) bila semua ruangan sudah selesai. Dalam satu transaksi: kondisi hasil
 * `kondisi_berubah` diterapkan ke aset (riwayat sumber `inventarisasi`), aset berstatus hilang yang ditemukan kembali
 * menjadi aktif, lalu snapshot berita acara disimpan. Aset `tidak_ditemukan` TIDAK otomatis hilang: menunggu verifikasi admin.
 */
class TutupPeriode
{
    public function handle(PeriodeInventarisasi $periode, User $pelaku): PeriodeInventarisasi
    {
        Gate::forUser($pelaku)->authorize('tutup', $periode);

        return LockAset::satu('aset:inventarisasi:buka', fn (): PeriodeInventarisasi => DB::transaction(function () use ($periode, $pelaku): PeriodeInventarisasi {
            /** @var PeriodeInventarisasi $terkunci */
            $terkunci = PeriodeInventarisasi::query()->lockForUpdate()->findOrFail($periode->getKey());

            if ($terkunci->status !== StatusPeriodeInventarisasi::Berjalan) {
                throw ValidationException::withMessages(['status' => "Periode berstatus {$terkunci->status->label()}; hanya periode berjalan yang dapat ditutup."]);
            }

            $belum = $terkunci->ruangan()->with('ruangan')->where('status', '!=', StatusInventarisasiRuangan::Selesai->value)->get();

            if ($belum->isNotEmpty()) {
                throw ValidationException::withMessages(['ruangan' => 'Ruangan berikut belum selesai diinventarisasi: '.$belum->map(fn ($r) => $r->ruangan->nama)->implode(', ').'.']);
            }

            // Snapshot disusun SEBELUM kondisi diterapkan agar "kondisi data" pada berita acara adalah kondisi sebelum inventarisasi.
            $snapshot = app(BeritaAcaraInventarisasi::class)->susun($terkunci, $pelaku);
            $this->terapkanHasil($terkunci, $pelaku);
            $terkunci->update(['status' => StatusPeriodeInventarisasi::Ditutup, 'ditutup_pada' => now(), 'berita_acara' => $snapshot]);
            $periode->setRawAttributes($terkunci->getAttributes(), true);

            return $terkunci;
        }));
    }

    private function terapkanHasil(PeriodeInventarisasi $periode, User $pelaku): void
    {
        $hasil = HasilInventarisasi::query()->with('aset')
            ->whereIn('inventarisasi_ruangan_id', $periode->ruangan()->pluck('id'))
            ->whereIn('hasil', [Hasil::KondisiBerubah->value, Hasil::Ditemukan->value])->whereNotNull('aset_id')->get();

        foreach ($hasil as $h) {
            $aset = $h->aset;

            if ($h->hasil === Hasil::KondisiBerubah && $h->kondisi_ditemukan !== null) {
                app(UbahKondisiAset::class)->handle($aset, $h->kondisi_ditemukan, $pelaku, 'inventarisasi', $periode->getKey(), "Inventarisasi \"{$periode->nama}\"");
            }

            if ($aset->fresh()?->status === StatusAset::Hilang) {
                app(UbahStatusAset::class)->handle($aset->fresh(), StatusAset::Aktif, $pelaku, "Ditemukan kembali pada inventarisasi \"{$periode->nama}\"", [], otorisasi: false);
            }
        }
    }
}
