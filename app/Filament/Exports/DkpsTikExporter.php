<?php

namespace App\Filament\Exports;

use App\Models\Aset;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * LAP-07 (RG-11): DKPS LAMDIK — Teknologi Informasi dan Komunikasi: aset aktif berkode barang peralatan komputer
 * (awalan `config('aset.dkps.awalan_kode_tik')`), dengan prodi pemakai ruangan lokasinya.
 */
class DkpsTikExporter extends Eksportir
{
    protected static ?string $model = Aset::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['ruangan.prodi'])
            ->where('status', 'aktif')
            ->where('kode_barang', 'like', config('aset.dkps.awalan_kode_tik').'%')
            ->orderBy('ruangan_id')->orderBy('nama');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('prodi')->label('Program Studi')->state(fn (Aset $r): string => $r->ruangan?->prodi->pluck('nama')->implode('; ') ?: '(umum)'),
            ExportColumn::make('nama')->label('Jenis Perangkat'),
            ExportColumn::make('merk_tipe')->label('Merk/Tipe'),
            ExportColumn::make('spesifikasi')->label('Spesifikasi'),
            ExportColumn::make('lokasi')->label('Lokasi')->state(fn (Aset $r): ?string => $r->ruangan?->nama),
            ExportColumn::make('tahun_perolehan')->label('Tahun Pengadaan'),
            ExportColumn::make('kondisi')->label('Kondisi')->state(fn (Aset $r): string => $r->kondisi->value),
            ExportColumn::make('kode_tampil')->label('Kode/NUP')->state(fn (Aset $r): string => $r->kode_tampil),
        ];
    }
}
