<?php

namespace App\Filament\Exports;

use App\Models\Aset;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * LAP-06 (RG-05): ekspor rekonsiliasi semesteran — kode barang + NUP, lokasi, kondisi, nilai — untuk dicocokkan dengan
 * aplikasi BMN resmi. Juga dipakai sebagai daftar aset lengkap (rekap LAP-02 tingkat barang).
 */
class AsetRekonsiliasiExporter extends Eksportir
{
    protected static ?string $model = Aset::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('ruangan')->orderBy('kode_barang')->orderBy('nup');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('kode_barang')->label('Kode Barang'),
            ExportColumn::make('nup')->label('NUP'),
            ExportColumn::make('kode_internal')->label('Kode Internal'),
            ExportColumn::make('status_bmn')->label('Status BMN')->state(fn (Aset $r): string => $r->status_bmn->value),
            ExportColumn::make('nama')->label('Nama Barang'),
            ExportColumn::make('merk_tipe')->label('Merk/Tipe'),
            ExportColumn::make('ruangan_kode')->label('Kode Ruangan')->state(fn (Aset $r): ?string => $r->ruangan?->kode),
            ExportColumn::make('ruangan_nama')->label('Lokasi')->state(fn (Aset $r): ?string => $r->ruangan !== null ? $r->ruangan->nama : $r->lokasi_lainnya),
            ExportColumn::make('kondisi')->label('Kondisi')->state(fn (Aset $r): string => $r->kondisi->value),
            ExportColumn::make('status')->label('Status')->state(fn (Aset $r): string => $r->status->value),
            ExportColumn::make('tahun_perolehan')->label('Tahun Perolehan'),
            ExportColumn::make('nilai_perolehan')->label('Nilai Perolehan (Rp)'),
        ];
    }
}
