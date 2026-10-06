<?php

namespace App\Filament\Resources\Penghapusan\Pages;

use App\Filament\Resources\Penghapusan\UsulanPenghapusanResource;
use Filament\Resources\Pages\ListRecords;

class DaftarUsulan extends ListRecords
{
    protected static string $resource = UsulanPenghapusanResource::class;

    protected function getHeaderActions(): array
    {
        return [UsulanPenghapusanResource::aksiBuat()];
    }
}
