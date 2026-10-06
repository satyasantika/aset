<?php

namespace App\Filament\Resources\KategoriRuangan\Pages;

use App\Filament\Resources\KategoriRuangan\KategoriRuanganResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class KelolaKategoriRuangan extends ManageRecords
{
    protected static string $resource = KategoriRuanganResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
