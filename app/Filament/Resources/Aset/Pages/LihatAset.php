<?php

namespace App\Filament\Resources\Aset\Pages;

use App\Filament\Resources\Aset\AsetResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class LihatAset extends ViewRecord
{
    protected static string $resource = AsetResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components(AsetResource::komponenData(identitasDapatDiubah: false))->disabled();
    }
}
