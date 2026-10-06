<?php

namespace App\Filament\Resources\Prodi\Pages;

use App\Filament\Resources\Prodi\ProdiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class KelolaProdi extends ManageRecords
{
    protected static string $resource = ProdiResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
