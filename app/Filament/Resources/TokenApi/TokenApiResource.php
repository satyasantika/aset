<?php

namespace App\Filament\Resources\TokenApi;

use App\Filament\Resources\TokenApi\Pages\DaftarTokenApi;
use App\Models\TokenAkses;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Token API klien eksternal (mis. Surat). Hanya super-admin (`pengaturan.kelola`); token dibuat lewat `BuatTokenKlien`, dicabut dengan menghapus. */
class TokenApiResource extends Resource
{
    protected static ?string $model = TokenAkses::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $modelLabel = 'token API';

    protected static ?string $pluralModelLabel = 'Token API';

    protected static ?string $navigationLabel = 'Token API';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 95;

    private static function boleh(): bool
    {
        /** @var User|null $pengguna */
        $pengguna = auth()->user();

        return (bool) $pengguna?->can('pengaturan.kelola');
    }

    public static function canViewAny(): bool
    {
        return self::boleh();
    }

    public static function canCreate(): bool
    {
        return self::boleh();
    }

    public static function canDelete(Model $record): bool
    {
        return self::boleh();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Klien')->searchable(),
                TextColumn::make('abilities')->label('Ability')->badge(),
                TextColumn::make('last_used_at')->label('Terakhir dipakai')->dateTime('d M Y H:i')->placeholder('Belum pernah'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([DeleteAction::make()->label('Cabut')->modalHeading('Cabut token ini?')]);
    }

    public static function getPages(): array
    {
        return ['index' => DaftarTokenApi::route('/')];
    }
}
