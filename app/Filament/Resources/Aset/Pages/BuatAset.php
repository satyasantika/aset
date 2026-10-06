<?php

namespace App\Filament\Resources\Aset\Pages;

use App\Actions\Aset\DaftarkanAset;
use App\Filament\Resources\Aset\AsetResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class BuatAset extends CreateRecord
{
    protected static string $resource = AsetResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $pelaku */
        $pelaku = auth()->user();

        return app(DaftarkanAset::class)->handle($data, $pelaku);
    }
}
