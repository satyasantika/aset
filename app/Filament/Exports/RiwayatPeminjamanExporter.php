<?php

namespace App\Filament\Exports;

use App\Models\Peminjaman;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Illuminate\Database\Eloquent\Builder;

/** LAP-04: riwayat & statistik peminjaman beserta keterlambatan. Nama/kontak/unit peminjam hanya untuk yang berhak (BR-23). */
class RiwayatPeminjamanExporter extends Eksportir
{
    protected static ?string $model = Peminjaman::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('item.aset')->orderByDesc('mulai');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('nomor')->label('Nomor'),
            ExportColumn::make('jenis')->label('Jenis Peminjam')->state(fn (Peminjaman $r): string => $r->jenis_peminjam->label()),
            ExportColumn::make('peminjam')->label('Peminjam')->state(fn (Peminjaman $r, Eksportir $exporter): string => $exporter->pribadi($r->nama_peminjam)),
            ExportColumn::make('unit')->label('Unit')->state(fn (Peminjaman $r, Eksportir $exporter): string => $exporter->pribadi($r->unit_peminjam)),
            ExportColumn::make('kontak')->label('Kontak')->state(fn (Peminjaman $r, Eksportir $exporter): string => $exporter->pribadi($r->kontak_peminjam)),
            ExportColumn::make('keperluan')->label('Keperluan'),
            ExportColumn::make('barang')->label('Barang')->state(fn (Peminjaman $r): string => $r->item->map(fn ($i) => $i->aset->nama.' ('.$i->aset->kode_tampil.')')->implode('; ')),
            ExportColumn::make('jumlah')->label('Jumlah Barang')->state(fn (Peminjaman $r): int => $r->item->count()),
            ExportColumn::make('mulai')->label('Mulai')->state(fn (Peminjaman $r): string => $r->mulai->format('Y-m-d H:i')),
            ExportColumn::make('rencana_kembali')->label('Rencana Kembali')->state(fn (Peminjaman $r): string => $r->rencana_kembali->format('Y-m-d H:i')),
            ExportColumn::make('dikembalikan_pada')->label('Dikembalikan')->state(fn (Peminjaman $r): ?string => ($r->dikembalikan_pada !== null ? Carbon::parse($r->dikembalikan_pada)->format('Y-m-d H:i') : null)),
            ExportColumn::make('status')->label('Status')->state(fn (Peminjaman $r): string => $r->status->label()),
            ExportColumn::make('terlambat')->label('Terlambat')->state(fn (Peminjaman $r): string => $r->terlambat() ? 'Ya' : 'Tidak'),
        ];
    }
}
