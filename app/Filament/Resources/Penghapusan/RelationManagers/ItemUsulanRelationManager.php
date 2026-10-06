<?php

namespace App\Filament\Resources\Penghapusan\RelationManagers;

use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Aset dalam usulan beserta status/kondisi terkini (baca-saja). */
class ItemUsulanRelationManager extends RelationManager
{
    protected static string $relationship = 'item';

    protected static ?string $title = 'Aset yang diusulkan';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('aset'))
            ->columns([
                TextColumn::make('aset.kode_tampil')->label('Kode / NUP'),
                TextColumn::make('aset.nama')->label('Nama')->searchable(),
                TextColumn::make('alasan_item')->label('Alasan')->badge()->formatStateUsing(fn (string $state) => $state === 'hilang' ? 'Hilang' : 'Rusak berat'),
                TextColumn::make('aset.kondisi')->label('Kondisi')->formatStateUsing(fn (KondisiAset $state) => $state->value),
                TextColumn::make('aset.status')->label('Status aset')->badge()->formatStateUsing(fn (StatusAset $state) => $state->label()),
                TextColumn::make('aset.nomor_sk_penghapusan')->label('No. SK')->placeholder('—'),
            ]);
    }
}
