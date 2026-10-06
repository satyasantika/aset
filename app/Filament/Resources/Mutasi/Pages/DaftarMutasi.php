<?php

namespace App\Filament\Resources\Mutasi\Pages;

use App\Filament\Resources\Mutasi\MutasiResource;
use Filament\Resources\Pages\ListRecords;

class DaftarMutasi extends ListRecords
{
    protected static string $resource = MutasiResource::class;

    protected function getHeaderActions(): array
    {
        return [MutasiResource::aksiAjukan()];
    }
}
