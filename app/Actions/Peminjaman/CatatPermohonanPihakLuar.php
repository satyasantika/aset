<?php

namespace App\Actions\Peminjaman;

use App\Enums\JenisPeminjam;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\User;
use App\Support\NomorTransaksi;
use App\Support\Pengaturan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-07: permohonan pihak luar TIDAK diproses sebagai peminjaman internal. Admin mencatatnya sebagai
 * `jenis_peminjam = pihak_luar` berstatus `diajukan`; keputusan ada pada pejabat-penatausahaan. Barang tidak dapat
 * diserahkan lewat sistem ini (skema pemanfaatan/sewa perlu verifikasi, 06-REKOMENDASI RG-07).
 */
class CatatPermohonanPihakLuar
{
    /**
     * @param  array<int, string>  $idAset
     * @param  array{nama_peminjam?: string|null, kontak_peminjam?: string|null, unit_peminjam?: string|null}  $pemohon
     */
    public function handle(array $idAset, array $pemohon, string $keperluan, CarbonInterface $mulai, CarbonInterface $selesai, User $pencatat): Peminjaman
    {
        Gate::forUser($pencatat)->authorize('catatPihakLuar', Peminjaman::class);
        Pengaturan::pastikanFitur('peminjaman');

        $ids = collect($idAset)->unique()->values();
        Konsep::validasiPengajuan($ids, $keperluan, $mulai, $selesai);

        if (blank($pemohon['nama_peminjam'] ?? null)) {
            throw ValidationException::withMessages(['nama_peminjam' => 'Nama pemohon wajib diisi.']);
        }

        $aset = Aset::query()->whereIn('id', $ids)->get();

        if ($aset->count() !== $ids->count()) {
            throw ValidationException::withMessages(['aset' => 'Ada aset yang tidak ditemukan.']);
        }

        return DB::transaction(fn (): Peminjaman => NomorTransaksi::buat('PJM', Peminjaman::class, 5, function (string $nomor) use ($aset, $pemohon, $keperluan, $mulai, $selesai, $pencatat): Peminjaman {
            $peminjaman = Peminjaman::query()->create([
                'nomor' => $nomor,
                'jenis_peminjam' => JenisPeminjam::PihakLuar,
                'peminjam_user_id' => null,
                'nama_peminjam' => trim($pemohon['nama_peminjam']),
                'kontak_peminjam' => $pemohon['kontak_peminjam'] ?? null,
                'unit_peminjam' => $pemohon['unit_peminjam'] ?? null,
                'keperluan' => trim($keperluan),
                'mulai' => $mulai,
                'rencana_kembali' => $selesai,
                'status' => StatusPeminjaman::Diajukan,
                'dicatat_oleh' => $pencatat->getKey(),
            ]);

            foreach ($aset as $a) {
                $peminjaman->item()->create(['aset_id' => $a->getKey(), 'kondisi_saat_pinjam' => $a->kondisi]);
            }

            return $peminjaman;
        }));
    }
}
