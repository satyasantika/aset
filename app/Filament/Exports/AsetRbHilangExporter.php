<?php

namespace App\Filament\Exports;

use App\Models\Aset;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/** LAP-03 (RG-03): daftar barang Rusak Berat dan daftar barang hilang — dasar usulan penghapusan. */
class AsetRbHilangExporter extends Eksportir
{
    protected static ?string $model = Aset::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('ruangan')->where(fn (Builder $q) => $q->where('kondisi', 'RB')->orWhere('status', 'hilang'))->orderBy('nama');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('jenis')->label('Daftar')->state(fn (Aset $r): string => $r->status->value === 'hilang' ? 'Barang Hilang' : 'Barang Rusak Berat'),
            ExportColumn::make('kode_barang')->label('Kode Barang'),
            ExportColumn::make('nup')->label('NUP'),
            ExportColumn::make('kode_internal')->label('Kode Internal'),
            ExportColumn::make('nama')->label('Nama Barang'),
            ExportColumn::make('merk_tipe')->label('Merk/Tipe'),
            ExportColumn::make('lokasi')->label('Lokasi Terakhir')->state(fn (Aset $r): ?string => $r->ruangan !== null ? $r->ruangan->nama : $r->lokasi_lainnya),
            ExportColumn::make('kondisi')->label('Kondisi')->state(fn (Aset $r): string => $r->kondisi->value),
            ExportColumn::make('status')->label('Status')->state(fn (Aset $r): string => $r->status->value),
            ExportColumn::make('tahun_perolehan')->label('Tahun Perolehan'),
            ExportColumn::make('nilai_perolehan')->label('Nilai Perolehan (Rp)'),
            ExportColumn::make('keterangan')->label('Keterangan'),
        ];
    }
}
