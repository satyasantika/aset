<?php

namespace App\Filament\Exports;

use App\Models\Aktivitas;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/** LAP-09: log aktivitas (siapa, kapan, apa) untuk audit. Hanya admin; isi sudah menyamarkan atribut rahasia. */
class LogAktivitasExporter extends Eksportir
{
    protected static ?string $model = Aktivitas::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['causer', 'subject'])->orderByDesc('created_at');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Waktu')->state(fn (Aktivitas $r): string => $r->created_at->format('Y-m-d H:i:s')),
            ExportColumn::make('causer')->label('Pelaku')->state(fn (Aktivitas $r): ?string => $r->causer?->getAttribute('name')),
            ExportColumn::make('log_name')->label('Log'),
            ExportColumn::make('event')->label('Kejadian'),
            ExportColumn::make('description')->label('Keterangan'),
            ExportColumn::make('subject_type')->label('Objek')->state(fn (Aktivitas $r): ?string => $r->subject_type ? class_basename($r->subject_type) : null),
            ExportColumn::make('subject_id')->label('ID Objek'),
        ];
    }
}
