<?php

namespace App\Filament\Resources\Peminjaman\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Aset dalam peminjaman beserta kondisi saat pinjam/kembali (baca-saja). */
class ItemPeminjamanRelationManager extends RelationManager
{
    protected static string $relationship = 'item';

    protected static ?string $title = 'Aset yang dipinjam';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('aset'))
            ->columns([
                TextColumn::make('aset.kode_tampil')->label('Kode / NUP'),
                TextColumn::make('aset.nama')->label('Nama'),
                TextColumn::make('kondisi_saat_pinjam')->label('Kondisi saat pinjam')->formatStateUsing(fn ($state) => $state->value),
                TextColumn::make('kondisi_saat_kembali')->label('Kondisi saat kembali')->formatStateUsing(fn ($state) => $state?->value)->placeholder('—'),
                TextColumn::make('catatan')->label('Catatan')->wrap()->placeholder('—'),
            ]);
    }
}
