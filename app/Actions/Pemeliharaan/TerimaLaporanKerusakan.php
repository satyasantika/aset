<?php

namespace App\Actions\Pemeliharaan;

use App\Enums\StatusAset;
use App\Events\LaporanKerusakanDiterima;
use App\Models\Aset;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use App\Support\HashIp;
use Illuminate\Validation\ValidationException;

/**
 * BR-12 / US-PML-01: menerima laporan kerusakan dari publik (tanpa login, via QR) atau civitas/staf login, lalu membuka
 * tiket `baru` bagi PIC ruangan. Nama & kontak opsional; IP hanya disimpan sebagai HMAC harian. Tidak mengubah status
 * aset (PIC yang menilai). Memancarkan `LaporanKerusakanDiterima` untuk notifikasi (F11).
 */
class TerimaLaporanKerusakan
{
    public const MAKS_DESKRIPSI = 2000;

    public function handle(Aset $aset, string $deskripsi, ?string $nama = null, ?string $kontak = null, ?string $ip = null, ?User $pelapor = null): TiketPemeliharaan
    {
        $deskripsi = trim($deskripsi);

        if (mb_strlen($deskripsi) < 5) {
            throw ValidationException::withMessages(['deskripsi' => 'Jelaskan kerusakan minimal 5 karakter.']);
        }

        if (mb_strlen($deskripsi) > self::MAKS_DESKRIPSI) {
            throw ValidationException::withMessages(['deskripsi' => 'Deskripsi terlalu panjang (maksimal '.self::MAKS_DESKRIPSI.' karakter).']);
        }

        if ($aset->status === StatusAset::Dihapus) {
            throw ValidationException::withMessages(['aset' => 'Barang ini sudah tidak tercatat.']);
        }

        $sumber = match (true) {
            $pelapor === null => 'publik',
            $pelapor->can('pemeliharaan.kelola') => 'pic',
            default => 'civitas',
        };

        $tiket = app(BukaTiketPemeliharaan::class)->handle($aset, $sumber, null, $deskripsi, $pelapor, false, [
            'nama_pelapor' => $this->bersih($nama, 150) ?? $pelapor?->name,
            'kontak_pelapor' => $this->bersih($kontak, 50),
            'pelapor_user_id' => $pelapor?->getKey(),
            'ip_hash' => $ip !== null && $pelapor === null ? HashIp::dari($ip) : null,
        ]);

        LaporanKerusakanDiterima::dispatch($tiket);

        return $tiket;
    }

    private function bersih(?string $nilai, int $maks): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : mb_substr($nilai, 0, $maks);
    }
}
