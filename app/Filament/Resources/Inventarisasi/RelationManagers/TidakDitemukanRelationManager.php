<?php

namespace App\Filament\Resources\Inventarisasi\RelationManagers;

use App\Actions\Inventarisasi\TetapkanAsetHilang;
use App\Enums\HasilInventarisasi;
use App\Enums\StatusAset;
use App\Models\HasilInventarisasi as ModelHasil;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Daftar verifikasi admin: aset tidak ditemukan → tetapkan hilang (BR-14). */
class TidakDitemukanRelationManager extends RelationManager
{
    protected static string $relationship = 'hasil';

    protected static ?string $title = 'Verifikasi barang tidak ditemukan';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('hasil_inventarisasi.hasil', HasilInventarisasi::TidakDitemukan->value)->with(['aset', 'inventarisasiRuangan.ruangan', 'inventarisasiRuangan.periode']))
            ->columns([
                TextColumn::make('inventarisasiRuangan.ruangan.nama')->label('Ruangan'),
                TextColumn::make('aset.kode_tampil')->label('Kode / NUP'),
                TextColumn::make('aset.nama')->label('Nama')->searchable(),
                TextColumn::make('aset.status')->label('Status aset')->badge()->formatStateUsing(fn (StatusAset $state) => $state->label()),
            ])
            ->recordActions([
                Action::make('tetapkanHilang')->label('Tetapkan hilang')->icon('heroicon-o-exclamation-circle')->color('danger')
                    ->visible(fn (ModelHasil $record): bool => $record->aset?->status === StatusAset::Aktif && (auth()->user()?->can('verifikasi', $record->inventarisasiRuangan->periode) ?? false))
                    ->schema([Textarea::make('catatan')->label('Catatan verifikasi')->required()->maxLength(1000)])
                    ->action(function (ModelHasil $record, array $data): void {
                        app(TetapkanAsetHilang::class)->handle($record, auth()->user(), $data['catatan']);
                        Notification::make()->success()->title('Aset ditetapkan hilang')->send();
                    }),
            ]);
    }
}
