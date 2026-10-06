<?php

namespace App\Actions\Peminjaman;

use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-08 + BR-11: apakah satu aset boleh dipinjam pada rentang [mulai, selesai)?
 *
 * Tertolak bila aset tidak aktif, Rusak Berat, atau tidak ditandai dapat dipinjam (BR-11), atau ada peminjaman
 * `disetujui`/`dipinjam` yang rentangnya tumpang tindih. Rentang yang hanya bersinggungan di batas (selesai = mulai
 * peminjaman lain) diperbolehkan. Peminjaman `dipinjam` yang sudah lewat rencana kembali tetapi belum dikembalikan
 * tetap menahan aset (barang masih di luar). Peminjaman `diajukan` tidak menahan aset.
 */
class CekKetersediaan
{
    /** Alasan aset tidak tersedia, atau null bila tersedia. */
    public function alasan(Aset $aset, CarbonInterface $mulai, CarbonInterface $selesai, ?string $kecualiPeminjamanId = null): ?string
    {
        if ($selesai->lessThanOrEqualTo($mulai)) {
            return 'Rencana kembali harus setelah waktu mulai.';
        }

        $nama = "{$aset->nama} ({$aset->kode_tampil})";

        if ($aset->status !== StatusAset::Aktif) {
            return "{$nama} berstatus {$aset->status->label()} sehingga tidak dapat dipinjam.";
        }

        if ($aset->kondisi === KondisiAset::RusakBerat) {
            return "{$nama} berkondisi Rusak Berat sehingga tidak dapat dipinjam.";
        }

        if (! $aset->dapat_dipinjam) {
            return "{$nama} tidak ditandai dapat dipinjam.";
        }

        $bentrok = $this->peminjamanBentrok($aset, $mulai, $selesai, $kecualiPeminjamanId);

        if ($bentrok !== null) {
            return "{$nama} tidak tersedia pada rentang tersebut (bentrok dengan {$bentrok}).";
        }

        return null;
    }

    public function tersedia(Aset $aset, CarbonInterface $mulai, CarbonInterface $selesai, ?string $kecualiPeminjamanId = null): bool
    {
        return $this->alasan($aset, $mulai, $selesai, $kecualiPeminjamanId) === null;
    }

    /** @throws ValidationException */
    public function pastikan(Aset $aset, CarbonInterface $mulai, CarbonInterface $selesai, ?string $kecualiPeminjamanId = null): void
    {
        if (($alasan = $this->alasan($aset, $mulai, $selesai, $kecualiPeminjamanId)) !== null) {
            throw ValidationException::withMessages(['aset' => $alasan]);
        }
    }

    /** Nomor peminjaman pertama yang bentrok, atau null. */
    private function peminjamanBentrok(Aset $aset, CarbonInterface $mulai, CarbonInterface $selesai, ?string $kecuali): ?string
    {
        $kunci = collect(StatusPeminjaman::cases())->filter(fn (StatusPeminjaman $s) => $s->mengunciAset())->map->value->all();
        $sekarang = now();

        $bentrok = DB::table('peminjaman_item')
            ->join('peminjaman', 'peminjaman.id', '=', 'peminjaman_item.peminjaman_id')
            ->where('peminjaman_item.aset_id', $aset->getKey())
            ->whereIn('peminjaman.status', $kunci)
            ->when($kecuali, fn ($q) => $q->where('peminjaman.id', '!=', $kecuali))
            ->where('peminjaman.mulai', '<', $selesai)
            ->where(function ($q) use ($mulai, $sekarang) {
                // akhir efektif: rencana kembali; bila sudah lewat dan masih dipinjam → tanpa batas
                $q->where('peminjaman.rencana_kembali', '>', $mulai)
                    ->orWhere(fn ($t) => $t->where('peminjaman.status', StatusPeminjaman::Dipinjam->value)
                        ->where('peminjaman.rencana_kembali', '<=', $sekarang));
            })
            ->orderBy('peminjaman.mulai')
            ->value('peminjaman.nomor');

        return $bentrok;
    }
}
