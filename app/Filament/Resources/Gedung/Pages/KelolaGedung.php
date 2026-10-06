<?php

namespace App\Filament\Resources\Gedung\Pages;

use App\Filament\Resources\Gedung\GedungResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class KelolaGedung extends ManageRecords
{
    protected static string $resource = GedungResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
