<?php

namespace App\Filament\Resources\KategoriRuangan;

use App\Filament\Resources\KategoriRuangan\Pages\KelolaKategoriRuangan;
use App\Models\KategoriRuangan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class KategoriRuanganResource extends Resource
{
    protected static ?string $model = KategoriRuangan::class;

    protected static ?string $slug = 'kategori-ruangan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'kategori ruangan';

    protected static ?string $pluralModelLabel = 'Kategori ruangan';

    protected static ?string $navigationLabel = 'Kategori ruangan';

    protected static string|\UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->label('Nama')->required()->maxLength(100)
                ->unique(KategoriRuangan::class, 'nama', ignoreRecord: true),
            Toggle::make('adalah_laboratorium')->label('Laboratorium (DKPS)'),
            Toggle::make('adalah_ruang_kelas')->label('Ruang kelas (DKPS)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                IconColumn::make('adalah_laboratorium')->label('Lab')->boolean(),
                IconColumn::make('adalah_ruang_kelas')->label('Kelas')->boolean(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => KelolaKategoriRuangan::route('/')];
    }
}
