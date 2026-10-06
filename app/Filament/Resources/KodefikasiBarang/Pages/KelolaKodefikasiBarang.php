<?php

namespace App\Filament\Resources\KodefikasiBarang\Pages;

use App\Filament\Imports\KodefikasiBarangImporter;
use App\Filament\Resources\KodefikasiBarang\KodefikasiBarangResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;

class KelolaKodefikasiBarang extends ManageRecords
{
    protected static string $resource = KodefikasiBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()->importer(KodefikasiBarangImporter::class)->label('Impor CSV'),
            CreateAction::make(),
        ];
    }
}
