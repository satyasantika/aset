<?php

namespace App\Filament\Exports;

use App\Models\TiketPemeliharaan;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/** LAP-05: rekap pemeliharaan & perbaikan. Identitas pelapor hanya untuk yang berhak (BR-23). */
class TiketPemeliharaanExporter extends Eksportir
{
    protected static ?string $model = TiketPemeliharaan::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['aset.ruangan', 'pelaporUser'])->orderByDesc('created_at');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('nomor')->label('Nomor'),
            ExportColumn::make('aset')->label('Barang')->state(fn (TiketPemeliharaan $r): string => $r->aset->nama.' ('.$r->aset->kode_tampil.')'),
            ExportColumn::make('lokasi')->label('Lokasi')->state(fn (TiketPemeliharaan $r): ?string => $r->aset->ruangan?->nama),
            ExportColumn::make('status')->label('Status')->state(fn (TiketPemeliharaan $r): string => $r->status->value),
            ExportColumn::make('deskripsi')->label('Deskripsi'),
            ExportColumn::make('dibuka')->label('Dibuka')->state(fn (TiketPemeliharaan $r): string => $r->created_at->format('Y-m-d H:i')),
            ExportColumn::make('selesai')->label('Selesai')->state(fn (TiketPemeliharaan $r): ?string => ($r->selesai_pada !== null ? Carbon::parse($r->selesai_pada)->format('Y-m-d H:i') : null)),
            ExportColumn::make('pelapor')->label('Pelapor')->state(fn (TiketPemeliharaan $r, Eksportir $exporter): string => $exporter->pribadi($r->nama_pelapor ?? $r->pelaporUser?->name)),
            ExportColumn::make('tindakan')->label('Tindakan'),
            ExportColumn::make('biaya')->label('Biaya (Rp)'),
        ];
    }
}
