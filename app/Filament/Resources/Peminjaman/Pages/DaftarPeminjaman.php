<?php

namespace App\Filament\Resources\Peminjaman\Pages;

use App\Enums\StatusPeminjaman;
use App\Filament\Resources\Peminjaman\PeminjamanResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class DaftarPeminjaman extends ListRecords
{
    protected static string $resource = PeminjamanResource::class;

    protected function getHeaderActions(): array
    {
        return [PeminjamanResource::aksiCatatPihakLuar()];
    }

    /** Tab: Aktif, Terlambat (dihitung, bukan status — BR-09), Diajukan, Riwayat. */
    public function getTabs(): array
    {
        $hitung = fn (callable $saring): int => $saring(PeminjamanResource::getEloquentQuery())->count();

        $aktif = fn (Builder $query): Builder => $query->whereIn('status', [StatusPeminjaman::Disetujui->value, StatusPeminjaman::Dipinjam->value]);
        $terlambat = fn (Builder $query): Builder => $query->where('status', StatusPeminjaman::Dipinjam->value)->where('rencana_kembali', '<', now());
        $diajukan = fn (Builder $query): Builder => $query->where('status', StatusPeminjaman::Diajukan->value);
        $riwayat = fn (Builder $query): Builder => $query->whereIn('status', [StatusPeminjaman::Dikembalikan->value, StatusPeminjaman::Ditolak->value, StatusPeminjaman::Dibatalkan->value]);

        return [
            'aktif' => Tab::make('Aktif')->modifyQueryUsing($aktif)->badge($hitung($aktif)),
            'terlambat' => Tab::make('Terlambat')->modifyQueryUsing($terlambat)->badge($hitung($terlambat))->badgeColor('danger'),
            'diajukan' => Tab::make('Diajukan')->modifyQueryUsing($diajukan)->badge($hitung($diajukan))->badgeColor('warning'),
            'riwayat' => Tab::make('Riwayat')->modifyQueryUsing($riwayat),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'aktif';
    }
}
