<?php

namespace App\Filament\Resources\Dbr\Pages;

use App\Filament\Resources\Dbr\DbrResource;
use Filament\Resources\Pages\ListRecords;

class DaftarDbr extends ListRecords
{
    protected static string $resource = DbrResource::class;

    protected function getHeaderActions(): array
    {
        return [DbrResource::aksiBangkitkan()];
    }
}
