<?php

namespace App\Filament\Resources\Pengguna;

use App\Filament\Resources\Pengguna\Pages\CreatePengguna;
use App\Filament\Resources\Pengguna\Pages\EditPengguna;
use App\Filament\Resources\Pengguna\Pages\ListPengguna;
use App\Filament\Resources\Pengguna\Schemas\PenggunaForm;
use App\Filament\Resources\Pengguna\Tables\PenggunaTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PenggunaResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?int $navigationSort = 80;

    protected static ?string $slug = 'pengguna';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PenggunaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PenggunaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengguna::route('/'),
            'create' => CreatePengguna::route('/create'),
            'edit' => EditPengguna::route('/{record}/edit'),
        ];
    }
}
