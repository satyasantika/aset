<?php

namespace App\Filament\Resources\TiketPemeliharaan\Pages;

use App\Actions\Pemeliharaan\SelesaikanTiket;
use App\Actions\Pemeliharaan\UbahStatusTiket;
use App\Enums\KondisiAset;
use App\Enums\StatusTiket;
use App\Filament\Resources\TiketPemeliharaan\TiketPemeliharaanResource;
use App\Models\TiketPemeliharaan;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class LihatTiket extends ViewRecord
{
    protected static string $resource = TiketPemeliharaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('proses')->label('Proses')->icon('heroicon-o-play')->color('info')
                ->visible(fn (): bool => $this->bisa(StatusTiket::Baru) || $this->bisa(StatusTiket::MenungguSukuCadang))
                ->action(fn () => $this->jalankan(fn () => app(UbahStatusTiket::class)->handle($this->tiket(), StatusTiket::Diproses, auth()->user()), 'Tiket diproses')),
            Action::make('menunggu')->label('Menunggu suku cadang')->icon('heroicon-o-clock')->color('gray')
                ->visible(fn (): bool => $this->bisa(StatusTiket::Diproses))
                ->action(fn () => $this->jalankan(fn () => app(UbahStatusTiket::class)->handle($this->tiket(), StatusTiket::MenungguSukuCadang, auth()->user()), 'Status diperbarui')),
            Action::make('selesaikan')->label('Selesaikan')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (): bool => $this->tiket()->status->aktif() && (auth()->user()?->can('kelola', $this->tiket()) ?? false))
                ->schema([
                    Textarea::make('tindakan')->label('Tindakan yang dilakukan')->required()->maxLength(2000),
                    TextInput::make('biaya')->label('Biaya (Rp)')->numeric()->minValue(0)->step('0.01'),
                    Select::make('kondisi')->label('Kondisi akhir aset')->options(KondisiAset::opsi()),
                ])
                ->action(fn (array $data) => $this->jalankan(fn () => app(SelesaikanTiket::class)->handle(
                    $this->tiket(), auth()->user(), $data['tindakan'], $data['biaya'] ?? null,
                    filled($data['kondisi'] ?? null) ? KondisiAset::from($data['kondisi']) : null,
                ), 'Tiket diselesaikan')),
            Action::make('tidakDapatDiperbaiki')->label('Tidak dapat diperbaiki')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (): bool => $this->tiket()->status->aktif() && (auth()->user()?->can('kelola', $this->tiket()) ?? false))
                ->schema([
                    Textarea::make('tindakan')->label('Alasan / tindakan')->required()->maxLength(2000),
                    TextInput::make('biaya')->label('Biaya (Rp)')->numeric()->minValue(0)->step('0.01'),
                ])
                ->action(fn (array $data) => $this->jalankan(fn () => app(SelesaikanTiket::class)->handle(
                    $this->tiket(), auth()->user(), $data['tindakan'], $data['biaya'] ?? null, null, StatusTiket::TidakDapatDiperbaiki,
                ), 'Tiket ditutup: tidak dapat diperbaiki')),
        ];
    }

    private function tiket(): TiketPemeliharaan
    {
        /** @var TiketPemeliharaan $record */
        $record = $this->getRecord();

        return $record;
    }

    private function bisa(StatusTiket $status): bool
    {
        return $this->tiket()->status === $status && (auth()->user()?->can('kelola', $this->tiket()) ?? false);
    }

    private function jalankan(callable $aksi, string $pesan): void
    {
        $aksi();
        Notification::make()->success()->title($pesan)->send();
        $this->refreshFormData(['status', 'tindakan', 'biaya', 'selesai_pada', 'ditangani_oleh']);
    }
}
