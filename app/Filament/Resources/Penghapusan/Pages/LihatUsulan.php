<?php

namespace App\Filament\Resources\Penghapusan\Pages;

use App\Actions\Penghapusan\AjukanUsulan;
use App\Actions\Penghapusan\BatalkanUsulan;
use App\Actions\Penghapusan\CatatSkPenghapusan;
use App\Actions\Penghapusan\SetujuiUsulan;
use App\Actions\Penghapusan\TolakUsulan;
use App\Enums\StatusUsulanHapus;
use App\Filament\Resources\Penghapusan\UsulanPenghapusanResource;
use App\Models\UsulanPenghapusan;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class LihatUsulan extends ViewRecord
{
    protected static string $resource = UsulanPenghapusanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajukan')->label('Ajukan')->icon('heroicon-o-paper-airplane')
                ->visible(fn (): bool => $this->bisa(StatusUsulanHapus::Draf, 'kelola'))->requiresConfirmation()
                ->action(fn () => $this->jalankan(fn () => app(AjukanUsulan::class)->handle($this->usulan(), auth()->user()), 'Usulan diajukan')),
            Action::make('setujui')->label('Setujui internal')->icon('heroicon-o-check')->color('success')
                ->visible(fn (): bool => $this->bisa(StatusUsulanHapus::Diajukan, 'putuskan'))->requiresConfirmation()
                ->modalDescription('Status setiap aset menjadi "diusulkan hapus".')
                ->schema([Textarea::make('catatan')->label('Catatan (opsional)')->maxLength(1000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(SetujuiUsulan::class)->handle($this->usulan(), auth()->user(), $data['catatan'] ?? null), 'Usulan disetujui internal')),
            Action::make('tolak')->label('Kembalikan ke draf')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                ->visible(fn (): bool => $this->bisa(StatusUsulanHapus::Diajukan, 'putuskan'))
                ->schema([Textarea::make('catatan')->label('Alasan')->required()->maxLength(1000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(TolakUsulan::class)->handle($this->usulan(), auth()->user(), $data['catatan']), 'Usulan dikembalikan')),
            Action::make('catatSk')->label('Catat SK penghapusan')->icon('heroicon-o-document-check')->color('success')
                ->visible(fn (): bool => $this->bisa(StatusUsulanHapus::DisetujuiInternal, 'kelola'))
                ->schema([
                    TextInput::make('nomor_sk')->label('Nomor SK')->required()->maxLength(100),
                    DatePicker::make('tanggal_sk')->label('Tanggal SK')->required()->maxDate(now()),
                    TextInput::make('url_sk')->label('Tautan SK (Google Drive)')->url()->maxLength(2048),
                ])
                ->action(fn (array $data) => $this->jalankan(fn () => app(CatatSkPenghapusan::class)->handle(
                    $this->usulan(), $data['nomor_sk'], Carbon::parse($data['tanggal_sk']), $data['url_sk'] ?? null, auth()->user(),
                ), 'SK dicatat; aset berstatus dihapus')),
            Action::make('batalkan')->label('Batalkan usulan')->icon('heroicon-o-x-mark')->color('danger')
                ->visible(fn (): bool => $this->usulan()->status->terbuka() && (auth()->user()?->can('kelola', $this->usulan()) ?? false))->requiresConfirmation()
                ->action(fn () => $this->jalankan(fn () => app(BatalkanUsulan::class)->handle($this->usulan(), auth()->user()), 'Usulan dibatalkan')),
        ];
    }

    private function usulan(): UsulanPenghapusan
    {
        /** @var UsulanPenghapusan $record */
        $record = $this->getRecord();

        return $record;
    }

    private function bisa(StatusUsulanHapus $status, string $kemampuan): bool
    {
        return $this->usulan()->status === $status && (auth()->user()?->can($kemampuan, $this->usulan()) ?? false);
    }

    private function jalankan(callable $aksi, string $pesan): void
    {
        $aksi();
        Notification::make()->success()->title($pesan)->send();
        $this->refreshFormData(['status', 'nomor_sk', 'tanggal_sk', 'catatan_keputusan', 'diputuskan_pada']);
    }
}
