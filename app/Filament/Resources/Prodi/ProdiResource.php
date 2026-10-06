<?php

namespace App\Filament\Resources\Prodi;

use App\Filament\Resources\Prodi\Pages\KelolaProdi;
use App\Models\Prodi;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProdiResource extends Resource
{
    protected static ?string $model = Prodi::class;

    protected static ?string $slug = 'prodi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $modelLabel = 'program studi';

    protected static ?string $pluralModelLabel = 'Program studi';

    protected static ?string $navigationLabel = 'Program studi';

    protected static string|\UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->label('Kode')->required()->maxLength(20)->alphaDash()
                ->unique(Prodi::class, 'kode', ignoreRecord: true),
            TextInput::make('nama')->label('Nama')->required()->maxLength(150),
            TextInput::make('kode_eksternal')->label('Kode eksternal (PDDikti/SIAKAD)')->maxLength(50),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->defaultSort('kode')
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('kode_eksternal')->label('Kode eksternal')->toggleable(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => KelolaProdi::route('/')];
    }
}
