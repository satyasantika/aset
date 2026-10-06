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
 * US-PJM-02: civitas mengajukan peminjaman online (status `diajukan`). Ketersediaan dicek saat mengajukan sehingga
 * peminjam langsung tahu bila bentrok; pengecekan final dilakukan ulang dalam lock saat persetujuan (BR-08).
 * Satu pengajuan hanya untuk aset dalam SATU ruangan agar satu PIC yang memutuskan. Pihak luar tidak dapat memakai
 * jalur ini (BR-07).
 */
class AjukanPeminjaman
{
    /**
     * @param  array<int, string>  $idAset
     */
    public function handle(array $idAset, string $keperluan, CarbonInterface $mulai, CarbonInterface $rencanaKembali, User $pemohon, ?string $unit = null): Peminjaman
    {
        Gate::forUser($pemohon)->authorize('ajukan', Peminjaman::class);
        Pengaturan::pastikanFitur('peminjaman');

        $ids = collect($idAset)->unique()->values();
        Konsep::validasiPengajuan($ids, $keperluan, $mulai, $rencanaKembali);

        $aset = Aset::query()->whereIn('id', $ids)->get();
        $masalah = [];

        foreach ($ids as $id) {
            /** @var Aset|null $a */
            $a = $aset->firstWhere('id', $id);

            if ($a === null) {
                $masalah[] = "Aset {$id} tidak ditemukan.";
            } elseif (($alasan = app(CekKetersediaan::class)->alasan($a, $mulai, $rencanaKembali)) !== null) {
                $masalah[] = $alasan;
            }
        }

        if ($aset->pluck('ruangan_id')->unique()->count() > 1) {
            $masalah[] = 'Satu pengajuan hanya boleh berisi aset dari satu ruangan. Ajukan terpisah untuk ruangan lain.';
        }

        if ($masalah !== []) {
            throw ValidationException::withMessages(['aset' => $masalah]);
        }

        return DB::transaction(fn (): Peminjaman => NomorTransaksi::buat('PJM', Peminjaman::class, 5, function (string $nomor) use ($aset, $keperluan, $mulai, $rencanaKembali, $pemohon, $unit): Peminjaman {
            $peminjaman = Peminjaman::query()->create([
                'nomor' => $nomor,
                'jenis_peminjam' => JenisPeminjam::Civitas,
                'peminjam_user_id' => $pemohon->getKey(),
                'nama_peminjam' => $pemohon->name,
                'kontak_peminjam' => $pemohon->no_hp,
                'unit_peminjam' => $unit,
                'keperluan' => trim($keperluan),
                'mulai' => $mulai,
                'rencana_kembali' => $rencanaKembali,
                'status' => StatusPeminjaman::Diajukan,
                'dicatat_oleh' => $pemohon->getKey(),
            ]);

            foreach ($aset as $a) {
                $peminjaman->item()->create(['aset_id' => $a->getKey(), 'kondisi_saat_pinjam' => $a->kondisi]);
            }

            return $peminjaman;
        }));
    }
}
