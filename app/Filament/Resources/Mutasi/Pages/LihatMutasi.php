<?php

namespace App\Filament\Resources\Mutasi\Pages;

use App\Actions\Mutasi\BatalkanMutasi;
use App\Actions\Mutasi\SetujuiMutasi;
use App\Actions\Mutasi\TolakMutasi;
use App\Filament\Resources\Mutasi\MutasiResource;
use App\Models\Mutasi;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class LihatMutasi extends ViewRecord
{
    protected static string $resource = MutasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setujui')->label('Setujui')->color('success')->icon('heroicon-o-check')
                ->visible(fn (): bool => $this->bisa('putuskan'))
                ->requiresConfirmation()->modalDescription('Lokasi seluruh aset akan dipindahkan dan DBR kedua ruangan ditandai perlu diperbarui.')
                ->schema([Textarea::make('catatan')->label('Catatan (opsional)')->maxLength(1000)])
                ->action(function (array $data): void {
                    app(SetujuiMutasi::class)->handle($this->mutasi(), auth()->user(), $data['catatan'] ?? null);
                    Notification::make()->success()->title('Mutasi disetujui')->send();
                    $this->refreshFormData(['status', 'diputuskan_oleh', 'diputuskan_pada', 'catatan_keputusan']);
                }),
            Action::make('tolak')->label('Tolak')->color('danger')->icon('heroicon-o-x-mark')
                ->visible(fn (): bool => $this->bisa('putuskan'))
                ->schema([Textarea::make('catatan')->label('Alasan penolakan')->required()->maxLength(1000)])
                ->action(function (array $data): void {
                    app(TolakMutasi::class)->handle($this->mutasi(), auth()->user(), $data['catatan']);
                    Notification::make()->success()->title('Mutasi ditolak')->send();
                    $this->refreshFormData(['status', 'diputuskan_oleh', 'diputuskan_pada', 'catatan_keputusan']);
                }),
            Action::make('batalkan')->label('Batalkan pengajuan')->color('gray')
                ->visible(fn (): bool => $this->bisa('batalkan'))
                ->requiresConfirmation()
                ->action(function (): void {
                    app(BatalkanMutasi::class)->handle($this->mutasi(), auth()->user());
                    Notification::make()->success()->title('Pengajuan dibatalkan')->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }

    private function mutasi(): Mutasi
    {
        /** @var Mutasi $record */
        $record = $this->getRecord();

        return $record;
    }

    private function bisa(string $kemampuan): bool
    {
        return $this->mutasi()->masihDiajukan() && (auth()->user()?->can($kemampuan, $this->mutasi()) ?? false);
    }
}
