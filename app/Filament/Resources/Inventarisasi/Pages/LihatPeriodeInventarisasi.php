<?php

namespace App\Filament\Resources\Inventarisasi\Pages;

use App\Actions\Inventarisasi\BukaPeriode;
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
        ];
    }

    private function periode(): PeriodeInventarisasi
    {
        /** @var PeriodeInventarisasi $record */
        $record = $this->getRecord();

        return $record;
    }
}
