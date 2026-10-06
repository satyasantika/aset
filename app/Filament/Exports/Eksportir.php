<?php

namespace App\Filament\Exports;

use App\Models\User;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Dasar semua laporan ekspor: antrean `ekspor` (Horizon), berkas di disk `tmp` (storage/app/tmp, dibersihkan ≤ 24 jam;
 * STANDAR-TEKNIS §1a: keluaran tidak disimpan permanen), format CSV & XLSX, dan penahanan data pribadi (BR-23).
 */
abstract class Eksportir extends Exporter
{
    public function getFileDisk(): string
    {
        return 'tmp';
    }

    public function getJobQueue(): ?string
    {
        return 'ekspor';
    }

    /** @return array<int, ExportFormat> */
    public function getFormats(): array
    {
        return [ExportFormat::Xlsx, ExportFormat::Csv];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $isi = 'Ekspor selesai: '.number_format($export->successful_rows).' baris.';

        if ($gagal = $export->getFailedRowsCount()) {
            $isi .= ' '.number_format($gagal).' baris gagal.';
        }

        return $isi;
    }

    /** Pengunduh berhak melihat data pribadi (nama/kontak peminjam & pelapor)? Pimpinan tidak (BR-23). */
    public function bolehDataPribadi(): bool
    {
        /** @var User|null $pengguna */
        $pengguna = $this->export->user;

        return $pengguna?->can('data-pribadi.lihat') ?? false;
    }

    /** Nilai kolom data pribadi, atau penanda bila pengunduh tidak berhak. */
    public function pribadi(?string $nilai): string
    {
        return $this->bolehDataPribadi() ? (string) $nilai : '(dirahasiakan)';
    }
}
