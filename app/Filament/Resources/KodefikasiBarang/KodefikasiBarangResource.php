<?php

namespace App\Filament\Resources\KodefikasiBarang;

use App\Filament\Resources\KodefikasiBarang\Pages\KelolaKodefikasiBarang;
use App\Models\KodefikasiBarang;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KodefikasiBarangResource extends Resource
{
    protected static ?string $model = KodefikasiBarang::class;

    protected static ?string $slug = 'kodefikasi-barang';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $modelLabel = 'kodefikasi barang';

    protected static ?string $pluralModelLabel = 'Kodefikasi barang BMN';

    protected static ?string $navigationLabel = 'Kodefikasi barang';

    protected static string|\UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 14;

    protected static ?string $recordTitleAttribute = 'uraian';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->label('Kode')->required()->maxLength(20)->regex('/^[0-9.]+$/')
                ->unique(KodefikasiBarang::class, 'kode', ignoreRecord: true),
            TextInput::make('uraian')->label('Uraian')->required()->maxLength(255),
            Select::make('tingkat')->label('Tingkat')->required()->options([
                1 => '1 — Golongan', 2 => '2 — Bidang', 3 => '3 — Kelompok', 4 => '4 — Sub kelompok', 5 => '5 — Sub-sub kelompok',
            ]),
            TextInput::make('induk_kode')->label('Kode induk')->maxLength(20),
            TextInput::make('kategori_lokal')->label('Kategori lokal (pemetaan SIMAN-2)')->maxLength(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('kode')
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('uraian')->label('Uraian')->searchable()->wrap(),
                TextColumn::make('tingkat')->label('Tingkat')->sortable(),
                TextColumn::make('kategori_lokal')->label('Kategori lokal')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tingkat')->label('Tingkat')->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5']),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => KelolaKodefikasiBarang::route('/')];
    }
}
