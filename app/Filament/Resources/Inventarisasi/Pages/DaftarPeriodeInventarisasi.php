<?php

namespace App\Filament\Resources\Inventarisasi\Pages;

use App\Filament\Resources\Inventarisasi\PeriodeInventarisasiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class DaftarPeriodeInventarisasi extends ListRecords
{
    protected static string $resource = PeriodeInventarisasiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat periode')];
    }
}
