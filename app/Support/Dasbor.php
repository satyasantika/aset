<?php

namespace App\Support;

use App\Enums\StatusAset;
use App\Enums\StatusDbr;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\DbrVersi;
use App\Models\Peminjaman;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Statistik dasbor (LAP-02, US-LAP-01). Cakupan: PIC hanya ruangannya (BR-05); admin, pejabat, dan pimpinan seluruh
 * fakultas — hanya angka agregat, tanpa data pribadi peminjam/pelapor (BR-23). Cache 30 menit per cakupan; kunci memuat
 * nomor versi sehingga `tandaiKedaluwarsa()` (dipanggil model yang memengaruhi angka) membersihkan semuanya sekaligus.
 */
class Dasbor
{
    public const TTL_MENIT = 30;

    public const KUNCI_VERSI = 'aset:statistik:versi';

    /** Peran dengan cakupan seluruh fakultas. */
    public const PERAN_GLOBAL = ['super-admin', 'admin-bmn', 'pejabat-penatausahaan', 'pimpinan'];

    public static function tandaiKedaluwarsa(): void
    {
        Cache::add(self::KUNCI_VERSI, 1, now()->addDays(30));
        Cache::increment(self::KUNCI_VERSI);
    }

    /** @return array<string, mixed> */
    public static function statistik(User $pengguna): array
    {
        $global = $pengguna->hasAnyRole(self::PERAN_GLOBAL);
        $cakupan = $global ? 'global' : 'pic:'.$pengguna->getKey();
        $versi = (int) Cache::get(self::KUNCI_VERSI, 1);

        return Cache::remember("aset:statistik:v{$versi}:{$cakupan}", now()->addMinutes(self::TTL_MENIT), fn (): array => self::hitung($pengguna, $global));
    }

    /** @return array<string, mixed> */
    private static function hitung(User $pengguna, bool $global): array
    {
        $aset = fn (): Builder => Aset::query()->where('status', '!=', StatusAset::Dihapus->value)
            ->when(! $global, fn (Builder $q) => $q->dikelolaOleh($pengguna));

        $nilai = fn (Builder $q): string => number_format((float) $q->sum('nilai_perolehan'), 2, '.', '');

        // Rincian (kondisi, ruangan, kategori) menghitung barang yang ada; aset hilang hanya muncul pada total & angka hilang.
        $ada = fn (): Builder => $aset()->where('status', '!=', StatusAset::Hilang->value);

        $perKondisi = [];
        foreach (['B', 'RR', 'RB'] as $k) {
            $q = $ada()->where('kondisi', $k);
            $perKondisi[$k] = ['jumlah' => (clone $q)->count(), 'nilai' => $nilai(clone $q)];
        }

        $perRuangan = $ada()->join('ruangan', 'ruangan.id', '=', 'aset.ruangan_id')
            ->groupBy('ruangan.id', 'ruangan.kode', 'ruangan.nama')
            ->selectRaw("ruangan.kode as kode, ruangan.nama as nama, count(*) as jumlah, coalesce(sum(aset.nilai_perolehan), 0) as nilai,
                sum(case when aset.kondisi = 'B' then 1 else 0 end) as b, sum(case when aset.kondisi = 'RR' then 1 else 0 end) as rr,
                sum(case when aset.kondisi = 'RB' then 1 else 0 end) as rb")
            ->orderByDesc('jumlah')->orderBy('ruangan.nama')->toBase()->get()
            ->map(fn ($r): array => [
                'kode' => $r->kode, 'nama' => $r->nama, 'jumlah' => (int) $r->jumlah, 'nilai' => number_format((float) $r->nilai, 2, '.', ''),
                'B' => (int) $r->b, 'RR' => (int) $r->rr, 'RB' => (int) $r->rb,
            ])->all();

        $perKategori = $ada()->leftJoin('kodefikasi_barang', 'kodefikasi_barang.kode', '=', 'aset.kode_barang')
            ->selectRaw("coalesce(nullif(kodefikasi_barang.kategori_lokal, ''), kodefikasi_barang.uraian, 'Tanpa kategori') as kategori, count(*) as jumlah, coalesce(sum(aset.nilai_perolehan), 0) as nilai")
            ->groupBy(DB::raw("coalesce(nullif(kodefikasi_barang.kategori_lokal, ''), kodefikasi_barang.uraian, 'Tanpa kategori')"))
            ->orderByDesc('jumlah')->toBase()->get()
            ->map(fn ($r): array => ['kategori' => $r->kategori, 'jumlah' => (int) $r->jumlah, 'nilai' => number_format((float) $r->nilai, 2, '.', '')])->all();

        $peminjaman = fn (): Builder => Peminjaman::query()->when(! $global, fn (Builder $q) => $q->terlihatOleh($pengguna));
        $tiket = fn (): Builder => TiketPemeliharaan::query()->when(! $global, fn (Builder $q) => $q->terlihatOleh($pengguna));
        $dbr = fn (): Builder => DbrVersi::query()->when(! $global, fn (Builder $q) => $q->terlihatOleh($pengguna));

        return [
            'dihitung_pada' => now()->toIso8601String(),
            'cakupan' => $global ? 'Seluruh fakultas' : 'Ruangan yang Anda kelola',
            'total' => ['jumlah' => $aset()->count(), 'nilai' => $nilai($aset())],
            'hilang' => $aset()->where('status', StatusAset::Hilang->value)->count(),
            'per_kondisi' => $perKondisi,
            'per_ruangan' => $perRuangan,
            'per_kategori' => $perKategori,
            'pinjaman_aktif' => $peminjaman()->where('status', StatusPeminjaman::Dipinjam->value)->count(),
            'pinjaman_terlambat' => $peminjaman()->where('status', StatusPeminjaman::Dipinjam->value)->where('rencana_kembali', '<', now())->count(),
            'tiket_terbuka' => $tiket()->aktif()->count(),
            'dbr_perlu_diperbarui' => $dbr()->where('status', StatusDbr::PerluDiperbarui->value)->count(),
        ];
    }
}
