<?php

namespace App\Filament\Resources\Inventarisasi\Pages;

use App\Actions\Inventarisasi\BukaPeriode;
use App\Actions\Inventarisasi\SahkanBeritaAcara;
use App\Actions\Inventarisasi\TutupPeriode;
use App\Enums\StatusPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\PeriodeInventarisasiResource;
use App\Models\PeriodeInventarisasi;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class LihatPeriodeInventarisasi extends ViewRecord
{
    protected static string $resource = PeriodeInventarisasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buka')->label('Buka periode')->icon('heroicon-o-play')->color('success')
                ->visible(fn (): bool => $this->periode()->status === StatusPeriodeInventarisasi::Rencana && (auth()->user()?->can('buka', $this->periode()) ?? false))
                ->requiresConfirmation()->modalDescription('Hanya satu periode yang boleh berjalan. Mutasi pada ruangan yang diinventarisasi akan ditahan.')
                ->action(function (): void {
                    app(BukaPeriode::class)->handle($this->periode(), auth()->user());
                    Notification::make()->success()->title('Periode dibuka')->send();
                    $this->refreshFormData(['status', 'dibuka_pada']);
                }),
            Action::make('tutup')->label('Tutup periode')->icon('heroicon-o-lock-closed')->color('warning')
                ->visible(fn (): bool => $this->periode()->status === StatusPeriodeInventarisasi::Berjalan && (auth()->user()?->can('tutup', $this->periode()) ?? false))
                ->requiresConfirmation()->modalDescription('Kondisi yang berubah diterapkan ke aset dan berita acara disusun. Semua ruangan harus sudah selesai.')
                ->action(function (): void {
                    app(TutupPeriode::class)->handle($this->periode(), auth()->user());
                    Notification::make()->success()->title('Periode ditutup; berita acara disusun')->send();
                    $this->refreshFormData(['status', 'ditutup_pada']);
                }),
            Action::make('sahkan')->label('Sahkan berita acara')->icon('heroicon-o-shield-check')->color('success')
                ->visible(fn (): bool => auth()->user()?->can('sahkan', $this->periode()) ?? false)
                ->requiresConfirmation()
                ->action(function (): void {
                    app(SahkanBeritaAcara::class)->handle($this->periode(), auth()->user());
                    Notification::make()->success()->title('Berita acara disahkan')->send();
                    $this->refreshFormData(['status', 'disahkan_pada']);
                }),
            Action::make('cetakBeritaAcara')->label('Berita acara (PDF)')->icon('heroicon-o-printer')
                ->visible(fn (): bool => auth()->user()?->can('lihatBeritaAcara', $this->periode()) ?? false)
                ->url(fn (): string => route('cetak.berita-acara', $this->periode()), shouldOpenInNewTab: true),
            Action::make('unduhSelisih')->label('Selisih (Excel)')->icon('heroicon-o-table-cells')
                ->visible(fn (): bool => auth()->user()?->can('lihatBeritaAcara', $this->periode()) ?? false)
                ->url(fn (): string => route('ekspor.inventarisasi.selisih', $this->periode())),
        ];
    }

    private function periode(): PeriodeInventarisasi
    {
        /** @var PeriodeInventarisasi $record */
        $record = $this->getRecord();

        return $record;
    }
}
