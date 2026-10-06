<?php

namespace App\Filament\Resources\Peminjaman\Pages;

use App\Filament\Resources\Peminjaman\PeminjamanResource;
use Filament\Resources\Pages\ListRecords;

class DaftarPeminjaman extends ListRecords
{
    protected static string $resource = PeminjamanResource::class;

    protected function getHeaderActions(): array
    {
        return [PeminjamanResource::aksiCatatPihakLuar()];
    }
}
