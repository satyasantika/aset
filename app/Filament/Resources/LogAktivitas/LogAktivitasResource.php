<?php

namespace App\Filament\Resources\LogAktivitas;

use App\Filament\Resources\LogAktivitas\Pages\ListLogAktivitas;
use App\Filament\Resources\LogAktivitas\Pages\ViewLogAktivitas;
use App\Filament\Resources\LogAktivitas\Schemas\LogAktivitasInfolist;
use App\Filament\Resources\LogAktivitas\Tables\LogAktivitasTable;
use App\Models\Aktivitas;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LogAktivitasResource extends Resource
{
    protected static ?string $model = Aktivitas::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'log aktivitas';

    protected static ?string $pluralModelLabel = 'Log aktivitas';

    protected static ?string $navigationLabel = 'Log aktivitas';

    protected static ?int $navigationSort = 90;

    public static function infolist(Schema $schema): Schema
    {
        return LogAktivitasInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LogAktivitasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogAktivitas::route('/'),
            'view' => ViewLogAktivitas::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
