<?php

namespace App\Actions\Peminjaman;

use App\Enums\JenisPeminjam;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\User;
use App\Support\LockAset;
use App\Support\NomorTransaksi;
use App\Support\Pengaturan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * US-PJM-01: PIC mencatat peminjaman beberapa aset (keranjang) untuk satu peminjam internal; langsung berstatus
 * `dipinjam` (BR-09). Semua-atau-tidak-sama-sekali (BR-08): satu aset tak tersedia → seluruh keranjang gagal.
 * Transaksi + lock Redis per aset + `lockForUpdate`; ketersediaan dicek ulang di dalam lock.
 */
class CatatPeminjamanLangsung
{
    public const MAKS_ITEM = 100;

    /**
     * @param  array<int, string>  $idAset
     * @param  array{peminjam_user_id?: string|null, nama_peminjam?: string|null, kontak_peminjam?: string|null, unit_peminjam?: string|null}  $peminjam
     */
    public function handle(array $idAset, array $peminjam, string $keperluan, CarbonInterface $rencanaKembali, User $pelaku, ?CarbonInterface $mulai = null): Peminjaman
    {
        Pengaturan::pastikanFitur('peminjaman');

        $ids = collect($idAset)->unique()->values();
        $mulai ??= now();

        $this->validasiMasukan($ids, $keperluan, $mulai, $rencanaKembali);
        $data = $this->dataPeminjam($peminjam);

        return LockAset::dengan('pinjam', $ids->all(), fn (): Peminjaman => DB::transaction(
            fn (): Peminjaman => $this->simpan($ids, $data, $keperluan, $mulai, $rencanaKembali, $pelaku),
        ));
    }

    /**
     * @param  Collection<int, string>  $ids
     * @param  array<string, mixed>  $data
     */
    private function simpan(Collection $ids, array $data, string $keperluan, CarbonInterface $mulai, CarbonInterface $rencanaKembali, User $pelaku): Peminjaman
    {
        $aset = Aset::query()->whereIn('id', $ids)->lockForUpdate()->orderBy('id')->get();
        $cek = app(CekKetersediaan::class);
        $masalah = [];

        foreach ($ids as $id) {
            /** @var Aset|null $a */
            $a = $aset->firstWhere('id', $id);

            if ($a === null) {
                $masalah[] = "Aset {$id} tidak ditemukan.";

                continue;
            }

            Gate::forUser($pelaku)->authorize('catat', [Peminjaman::class, $a]);

            if (($alasan = $cek->alasan($a, $mulai, $rencanaKembali)) !== null) {
                $masalah[] = $alasan;
            }
        }

        if ($masalah !== []) {
            throw ValidationException::withMessages(['aset' => $masalah]);
        }

        return NomorTransaksi::buat('PJM', Peminjaman::class, 5, function (string $nomor) use ($aset, $data, $keperluan, $mulai, $rencanaKembali, $pelaku): Peminjaman {
            $peminjaman = Peminjaman::query()->create([
                ...$data,
                'nomor' => $nomor,
                'jenis_peminjam' => JenisPeminjam::Civitas,
                'keperluan' => trim($keperluan),
                'mulai' => $mulai,
                'rencana_kembali' => $rencanaKembali,
                'status' => StatusPeminjaman::Dipinjam,
                'dicatat_oleh' => $pelaku->getKey(),
                'diserahkan_oleh' => $pelaku->getKey(),
                'diserahkan_pada' => now(),
            ]);

            foreach ($aset as $a) {
                $peminjaman->item()->create(['aset_id' => $a->getKey(), 'kondisi_saat_pinjam' => $a->kondisi]);
            }

            return $peminjaman;
        });
    }

    /** @param  Collection<int, string>  $ids */
    private function validasiMasukan(Collection $ids, string $keperluan, CarbonInterface $mulai, CarbonInterface $rencanaKembali): void
    {
        $galat = [];

        if ($ids->isEmpty()) {
            $galat['aset'] = 'Keranjang kosong.';
        } elseif ($ids->count() > self::MAKS_ITEM) {
            $galat['aset'] = 'Maksimal '.self::MAKS_ITEM.' aset per peminjaman.';
        }

        if (trim($keperluan) === '') {
            $galat['keperluan'] = 'Keperluan wajib diisi.';
        }

        $maks = (int) Pengaturan::ambil('maks_hari_pinjam', 14);

        if ($rencanaKembali->lessThanOrEqualTo($mulai)) {
            $galat['rencana_kembali'] = 'Rencana kembali harus setelah waktu mulai.';
        } elseif ($rencanaKembali->greaterThan($mulai->copy()->addDays($maks))) {
            $galat['rencana_kembali'] = "Rencana kembali melebihi batas maksimal {$maks} hari.";
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
    }

    /**
     * Peminjam internal: akun civitas terdaftar (nama diambil dari akun), atau nama + unit + kontak manual.
     * Pihak luar tidak diproses sebagai peminjaman biasa (BR-07).
     *
     * @param  array<string, mixed>  $peminjam
     * @return array<string, mixed>
     */
    private function dataPeminjam(array $peminjam): array
    {
        if (filled($peminjam['peminjam_user_id'] ?? null)) {
            $user = User::query()->where('aktif', true)->find($peminjam['peminjam_user_id']);

            if ($user === null) {
                throw ValidationException::withMessages(['peminjam_user_id' => 'Akun peminjam tidak ditemukan atau nonaktif.']);
            }

            return [
                'peminjam_user_id' => $user->getKey(),
                'nama_peminjam' => $user->name,
                'kontak_peminjam' => $peminjam['kontak_peminjam'] ?? $user->no_hp,
                'unit_peminjam' => $peminjam['unit_peminjam'] ?? null,
            ];
        }

        if (blank($peminjam['nama_peminjam'] ?? null)) {
            throw ValidationException::withMessages(['nama_peminjam' => 'Nama peminjam wajib diisi.']);
        }

        return [
            'peminjam_user_id' => null,
            'nama_peminjam' => trim((string) $peminjam['nama_peminjam']),
            'kontak_peminjam' => $peminjam['kontak_peminjam'] ?? null,
            'unit_peminjam' => $peminjam['unit_peminjam'] ?? null,
        ];
    }
}
