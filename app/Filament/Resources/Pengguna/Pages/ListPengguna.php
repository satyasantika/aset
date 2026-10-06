<?php

namespace App\Filament\Resources\Pengguna\Pages;

use App\Filament\Resources\Pengguna\PenggunaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPengguna extends ListRecords
{
    protected static string $resource = PenggunaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
