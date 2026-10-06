<?php

namespace App\Filament\Imports;

use App\Models\KodefikasiBarang;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;

/** Impor CSV kodefikasi: kode, uraian, tingkat, induk_kode, kategori_lokal. Memperbarui baris dengan kode yang sama. */
class KodefikasiBarangImporter extends Importer
{
    protected static ?string $model = KodefikasiBarang::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('kode')->label('Kode')->requiredMapping()->rules(['required', 'max:20', 'regex:/^[0-9.]+$/']),
            ImportColumn::make('uraian')->label('Uraian')->requiredMapping()->rules(['required', 'max:255']),
            ImportColumn::make('tingkat')->label('Tingkat')->requiredMapping()->numeric()->rules(['required', 'integer', 'between:1,5']),
            ImportColumn::make('induk_kode')->label('Kode induk')->rules(['nullable', 'max:20']),
            ImportColumn::make('kategori_lokal')->label('Kategori lokal')->rules(['nullable', 'max:100']),
        ];
    }

    public function resolveRecord(): ?Model
    {
        return KodefikasiBarang::query()->firstOrNew(['kode' => $this->data['kode']]);
    }

    public function getJobQueue(): ?string
    {
        return 'impor';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $isi = 'Impor kodefikasi barang selesai: '.number_format($import->successful_rows).' baris berhasil.';

        if ($gagal = $import->getFailedRowsCount()) {
            $isi .= ' '.number_format($gagal).' baris gagal.';
        }

        return $isi;
    }
}
