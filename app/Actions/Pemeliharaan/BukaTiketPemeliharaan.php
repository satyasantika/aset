<?php

namespace App\Actions\Pemeliharaan;

use App\Actions\Aset\UbahStatusAset;
use App\Enums\StatusAset;
use App\Models\Aset;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use App\Support\NomorTransaksi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Membuka tiket pemeliharaan (RG-08, BR-10, BR-12). Nomor `TKT-{tahun}-{4 digit}`.
 *
 * Idempoten per (aset, sumber, sumber_id) bila `sumber_id` diberikan: pemanggil otomatis (pengembalian peminjaman,
 * inventarisasi) tidak membuat tiket ganda. Action ini TIDAK mengotorisasi sendiri; pemanggil yang menjalankannya
 * (Action alur lain atau aksi UI yang memeriksa Policy `buka`) bertanggung jawab atas otorisasi.
 */
class BukaTiketPemeliharaan
{
    /**
     * @param  array{nama_pelapor?: string|null, kontak_pelapor?: string|null, pelapor_user_id?: string|null, ip_hash?: string|null}  $pelapor
     * @param  bool  $dalamPerbaikan  true → status aset menjadi `dalam_perbaikan` (tidak dapat dipinjam, BR-11).
     */
    public function handle(
        Aset $aset,
        string $sumber,
        ?string $sumberId,
        string $deskripsi,
        ?User $oleh = null,
        bool $dalamPerbaikan = false,
        array $pelapor = [],
    ): TiketPemeliharaan {
        if (! in_array($sumber, TiketPemeliharaan::SUMBER, true)) {
            throw ValidationException::withMessages(['sumber' => "Sumber tiket \"{$sumber}\" tidak dikenal."]);
        }

        if (trim($deskripsi) === '') {
            throw ValidationException::withMessages(['deskripsi' => 'Deskripsi kerusakan wajib diisi.']);
        }

        return DB::transaction(function () use ($aset, $sumber, $sumberId, $deskripsi, $oleh, $dalamPerbaikan, $pelapor): TiketPemeliharaan {
            if ($sumberId !== null) {
                $ada = TiketPemeliharaan::query()->where('aset_id', $aset->getKey())->where('sumber', $sumber)->where('sumber_id', $sumberId)->first();

                if ($ada !== null) {
                    return $ada;
                }
            }

            $tiket = NomorTransaksi::buat('TKT', TiketPemeliharaan::class, 4, fn (string $nomor): TiketPemeliharaan => TiketPemeliharaan::query()->create([
                'nomor' => $nomor,
                'aset_id' => $aset->getKey(),
                'sumber' => $sumber,
                'sumber_id' => $sumberId,
                'deskripsi' => trim($deskripsi),
                'nama_pelapor' => $pelapor['nama_pelapor'] ?? null,
                'kontak_pelapor' => $pelapor['kontak_pelapor'] ?? null,
                'pelapor_user_id' => $pelapor['pelapor_user_id'] ?? ($sumber === 'civitas' ? $oleh?->getKey() : null),
                'ip_hash' => $pelapor['ip_hash'] ?? null,
            ]));

            if ($dalamPerbaikan) {
                $this->tandaiDalamPerbaikan($aset, $oleh, $tiket);
            }

            return $tiket;
        });
    }

    private function tandaiDalamPerbaikan(Aset $aset, ?User $oleh, TiketPemeliharaan $tiket): void
    {
        $segar = $aset->fresh() ?? $aset;

        if ($segar->status !== StatusAset::Aktif) {
            return; // hanya aset aktif yang masuk perbaikan; status lain dipertahankan
        }

        app(UbahStatusAset::class)->handle($segar, StatusAset::DalamPerbaikan, $oleh ?? $this->sistem(), "Tiket {$tiket->nomor}", [], otorisasi: false);
    }

    /** Pelaku cadangan untuk riwayat status bila tiket dibuka tanpa pengguna (mis. laporan publik). */
    private function sistem(): User
    {
        return new User(['name' => 'Sistem']);
    }
}
