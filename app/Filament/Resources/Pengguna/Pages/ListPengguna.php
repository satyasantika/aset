<?php

namespace App\Filament\Resources\Pengguna\Pages;

use App\Filament\Imports\PenggunaImporter;
use App\Filament\Resources\Pengguna\PenggunaResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListPengguna extends ListRecords
{
    protected static string $resource = PenggunaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()->importer(PenggunaImporter::class)->label('Impor pengguna (CSV)'),
            CreateAction::make(),
        ];
    }
}
