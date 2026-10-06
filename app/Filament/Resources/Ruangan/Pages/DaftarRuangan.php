<?php

namespace App\Filament\Resources\Ruangan\Pages;

use App\Filament\Resources\Ruangan\RuanganResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class DaftarRuangan extends ListRecords
{
    protected static string $resource = RuanganResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
