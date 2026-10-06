<?php

namespace App\Filament\Resources\Aset\Pages;

use App\Actions\Aset\UbahDataAset;
use App\Filament\Resources\Aset\AsetResource;
use App\Models\Aset;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class UbahAset extends EditRecord
{
    protected static string $resource = AsetResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components(AsetResource::komponenData(identitasDapatDiubah: false));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $pelaku */
        $pelaku = auth()->user();
        /** @var Aset $record */

        return app(UbahDataAset::class)->handle($record, $data, $pelaku);
    }
}
