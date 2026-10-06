<?php

namespace App\Filament\Exports;

use App\Models\Aset;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * LAP-07 (RG-11): DKPS LAMDIK — Sarana Laboratorium dan Pembelajaran: aset aktif di ruangan berkategori laboratorium
 * atau ruang kelas, dengan prodi pemakai ruangan tersebut. Pemetaan kolom: docs/FORMAT-DKPS.md.
 */
class DkpsSaranaExporter extends Eksportir
{
    protected static ?string $model = Aset::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['ruangan.kategori', 'ruangan.prodi'])
            ->where('status', 'aktif')
            ->whereHas('ruangan.kategori', fn (Builder $q) => $q->where('adalah_laboratorium', true)->orWhere('adalah_ruang_kelas', true))
            ->orderBy('ruangan_id')->orderBy('nama');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('prodi')->label('Program Studi')->state(fn (Aset $r): string => $r->ruangan?->prodi->pluck('nama')->implode('; ') ?: '(umum)'),
            ExportColumn::make('jenis_ruangan')->label('Jenis Ruangan')->state(fn (Aset $r): ?string => $r->ruangan?->kategori?->nama),
            ExportColumn::make('ruangan')->label('Nama Ruangan')->state(fn (Aset $r): ?string => $r->ruangan?->nama),
            ExportColumn::make('nama')->label('Nama Sarana'),
            ExportColumn::make('merk_tipe')->label('Merk/Tipe'),
            ExportColumn::make('jumlah')->label('Jumlah Unit')->state(fn (): int => 1),
            ExportColumn::make('tahun_perolehan')->label('Tahun Pengadaan'),
            ExportColumn::make('kondisi')->label('Kondisi')->state(fn (Aset $r): string => $r->kondisi->value),
            ExportColumn::make('kode_tampil')->label('Kode/NUP')->state(fn (Aset $r): string => $r->kode_tampil),
        ];
    }
}
