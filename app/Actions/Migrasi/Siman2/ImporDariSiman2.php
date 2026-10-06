<?php

namespace App\Actions\Migrasi\Siman2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Spatie\Activitylog\ActivityLogStatus;

/**
 * Orkestrasi impor SIMAN-2 (XLSX) → SIMAN FKIP 3. Urutan: pengaturan → kategori ruangan → ruangan → pengguna & PIC →
 * (F7.2) inventaris → (F7.3) mutasi → peminjaman → log. Real: transaksi per langkah. Dry-run: seluruhnya dijalankan di
 * dalam satu transaksi yang SELALU di-rollback sehingga tidak ada data yang tertulis, tetapi galat basis data ikut
 * terdeteksi. Jejak audit dimatikan selama impor (riwayat lama diimpor eksplisit).
 */
class ImporDariSiman2
{
    private readonly ImporPengguna $pengguna;

    public function __construct()
    {
        $this->pengguna = new ImporPengguna;
    }

    /** @return list<Importer> */
    public function langkah(): array
    {
        return [
            new ImporPengaturan,
            new ImporKategoriRuangan,
            new ImporRuangan,
            $this->pengguna,
            new ImporInventaris,
        ];
    }

    /** @param  array<string, string>  $pemetaanPengguna */
    public function handle(string $berkas, bool $dryRun = false, bool $nupBerurutan = false, array $pemetaanPengguna = []): LaporanImpor
    {
        $xlsx = new BacaXlsx($berkas);
        $konteks = new Konteks($dryRun, $nupBerurutan, $pemetaanPengguna);
        $status = app(ActivityLogStatus::class);
        $semulaAktif = $status->disabled() === false;
        $status->disable();

        try {
            if ($dryRun) {
                DB::beginTransaction();
            }

            foreach ($this->langkah() as $langkah) {
                $dryRun
                    ? $langkah->jalankan($konteks, $xlsx)
                    : DB::transaction(fn () => $langkah->jalankan($konteks, $xlsx));
            }
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }

            $semulaAktif ? $status->enable() : $status->disable();
        }

        if (! $dryRun) {
            foreach ($this->pengguna->penggunaBaru as $surel) {
                Password::broker()->sendResetLink(['email' => $surel]);
            }
        }

        return $konteks->laporan;
    }
}
