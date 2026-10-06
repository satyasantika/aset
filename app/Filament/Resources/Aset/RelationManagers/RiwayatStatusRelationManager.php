<?php

namespace App\Filament\Resources\Aset\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Riwayat status (baca-saja). */
class RiwayatStatusRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayatStatus';

    protected static ?string $title = 'Riwayat status';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('pelaku'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i'),
                TextColumn::make('dari')->label('Dari')->placeholder('—'),
                TextColumn::make('ke')->label('Ke')->badge(),
                TextColumn::make('catatan')->label('Catatan')->wrap()->placeholder('—'),
                TextColumn::make('pelaku.name')->label('Oleh')->placeholder('Sistem'),
            ]);
    }
}
