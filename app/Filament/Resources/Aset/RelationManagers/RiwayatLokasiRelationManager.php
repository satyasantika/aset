<?php

namespace App\Filament\Resources\Aset\RelationManagers;

use App\Models\Ruangan;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Riwayat lokasi (baca-saja). */
class RiwayatLokasiRelationManager extends RelationManager
{
    protected static string $relationship = 'riwayatLokasi';

    protected static ?string $title = 'Riwayat lokasi';

    public function table(Table $table): Table
    {
        $namaRuangan = fn (?string $id): string => $id ? (string) Ruangan::withTrashed()->whereKey($id)->value('nama') : '—';

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('pelaku'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i'),
                TextColumn::make('dari_ruangan_id')->label('Dari')->formatStateUsing($namaRuangan),
                TextColumn::make('ke_ruangan_id')->label('Ke')->formatStateUsing($namaRuangan),
                TextColumn::make('sumber')->label('Sumber')->badge(),
                TextColumn::make('pelaku.name')->label('Oleh')->placeholder('Sistem'),
            ]);
    }
}
