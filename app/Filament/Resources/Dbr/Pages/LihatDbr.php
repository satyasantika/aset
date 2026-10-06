<?php

namespace App\Filament\Resources\Dbr\Pages;

use App\Actions\Dbr\KembalikanDbr;
use App\Actions\Dbr\SahkanDbr;
use App\Actions\Dbr\SetujuiDbrOlehPic;
use App\Enums\StatusDbr;
use App\Filament\Resources\Dbr\DbrResource;
use App\Models\DbrVersi;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class LihatDbr extends ViewRecord
{
    protected static string $resource = DbrResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cetak')->label('Cetak PDF')->icon('heroicon-o-printer')
                ->url(fn (): string => route('cetak.dbr', $this->dbr()), shouldOpenInNewTab: true),
            Action::make('setujuiPic')->label('Setujui (tanda tangan PIC)')->color('success')->icon('heroicon-o-check')
                ->visible(fn (): bool => $this->dbr()->status === StatusDbr::Draf && (auth()->user()?->can('setujuiPic', $this->dbr()) ?? false))
                ->requiresConfirmation()
                ->modalDescription('Isi daftar dibekukan sesuai data saat ini dan diteruskan ke pejabat penatausahaan.')
                ->action(fn () => $this->jalankan(fn () => app(SetujuiDbrOlehPic::class)->handle($this->dbr(), auth()->user()), 'DBR disetujui PIC')),
            Action::make('sahkan')->label('Sahkan')->color('success')->icon('heroicon-o-shield-check')
                ->visible(fn (): bool => $this->dbr()->status === StatusDbr::DisetujuiPic && (auth()->user()?->can('sahkan', $this->dbr()) ?? false))
                ->requiresConfirmation()
                ->action(fn () => $this->jalankan(fn () => app(SahkanDbr::class)->handle($this->dbr(), auth()->user()), 'DBR disahkan')),
            Action::make('kembalikan')->label('Kembalikan ke draf')->color('gray')->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (): bool => auth()->user()?->can('kembalikan', $this->dbr()) ?? false)
                ->schema([Textarea::make('catatan')->label('Alasan')->required()->maxLength(1000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(KembalikanDbr::class)->handle($this->dbr(), auth()->user(), $data['catatan']), 'DBR dikembalikan ke draf')),
        ];
    }

    private function dbr(): DbrVersi
    {
        /** @var DbrVersi $record */
        $record = $this->getRecord();

        return $record;
    }

    private function jalankan(callable $aksi, string $pesan): void
    {
        $aksi();
        Notification::make()->success()->title($pesan)->send();
        $this->refreshFormData(['status', 'snapshot', 'catatan', 'disetujui_pic_oleh', 'disahkan_oleh']);
    }
}
