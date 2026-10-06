<?php

namespace App\Filament\Resources\Mutasi\RelationManagers;

use App\Enums\KondisiAset;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Aset yang termasuk dalam mutasi (baca-saja). */
class AsetMutasiRelationManager extends RelationManager
{
    protected static string $relationship = 'aset';

    protected static ?string $title = 'Aset dalam mutasi';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_tampil')->label('Kode / NUP'),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('merk_tipe')->label('Merk/tipe'),
                TextColumn::make('kondisi')->label('Kondisi')->badge()->formatStateUsing(fn (KondisiAset $state) => $state->value),
            ]);
    }
}
