<?php

namespace App\Filament\Exports;

use App\Models\Ruangan;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/** LAP-07 (RG-11): DKPS LAMDIK — Prasarana Pendidikan: ruangan, luas, kapasitas, prodi pemakai, dan atribut K3L. */
class DkpsPrasaranaExporter extends Eksportir
{
    protected static ?string $model = Ruangan::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['gedung', 'kategori', 'prodi'])->orderBy('gedung_id')->orderBy('kode');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('prodi')->label('Program Studi')->state(fn (Ruangan $r): string => $r->prodi->pluck('nama')->implode('; ') ?: '(umum)'),
            ExportColumn::make('kode')->label('Kode Ruangan'),
            ExportColumn::make('nama')->label('Nama Ruangan'),
            ExportColumn::make('jenis')->label('Jenis Prasarana')->state(fn (Ruangan $r): ?string => $r->kategori?->nama),
            ExportColumn::make('gedung')->label('Gedung')->state(fn (Ruangan $r): ?string => $r->gedung?->nama),
            ExportColumn::make('luas_m2')->label('Luas (m²)'),
            ExportColumn::make('kapasitas')->label('Kapasitas'),
            ExportColumn::make('k3l')->label('K3L')->state(fn (Ruangan $r): string => implode('; ', $r->k3l ?? [])),
        ];
    }
}
