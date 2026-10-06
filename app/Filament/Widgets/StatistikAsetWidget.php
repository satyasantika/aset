<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\Dasbor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan dasbor: aset, nilai, kondisi, pinjaman aktif/terlambat, tiket terbuka, DBR perlu diperbarui (LAP-02). */
class StatistikAsetWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -5;

    public static function canView(): bool
    {
        return auth()->user()?->can('aset.lihat') ?? false;
    }

    protected function getHeading(): ?string
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return 'Ringkasan aset — '.Dasbor::statistik($pengguna)['cakupan'];
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $s = Dasbor::statistik($pengguna);
        $rp = fn (string $n): string => 'Rp '.number_format((float) $n, 0, ',', '.');

        return [
            Stat::make('Jumlah aset', number_format($s['total']['jumlah'], 0, ',', '.'))->description($s['hilang'].' di antaranya hilang'),
            Stat::make('Nilai perolehan', $rp($s['total']['nilai']))->description('Aset tercatat (belum dihapus)'),
            Stat::make('Kondisi Baik', number_format($s['per_kondisi']['B']['jumlah'], 0, ',', '.'))->color('success')->description($rp($s['per_kondisi']['B']['nilai'])),
            Stat::make('Rusak Ringan', number_format($s['per_kondisi']['RR']['jumlah'], 0, ',', '.'))->color('warning')->description($rp($s['per_kondisi']['RR']['nilai'])),
            Stat::make('Rusak Berat', number_format($s['per_kondisi']['RB']['jumlah'], 0, ',', '.'))->color('danger')->description($rp($s['per_kondisi']['RB']['nilai'])),
            Stat::make('Pinjaman aktif', (string) $s['pinjaman_aktif'])->description($s['pinjaman_terlambat'].' terlambat')->color($s['pinjaman_terlambat'] > 0 ? 'danger' : 'gray'),
            Stat::make('Tiket pemeliharaan terbuka', (string) $s['tiket_terbuka']),
            Stat::make('DBR perlu diperbarui', (string) $s['dbr_perlu_diperbarui'])->color($s['dbr_perlu_diperbarui'] > 0 ? 'warning' : 'gray'),
        ];
    }
}
