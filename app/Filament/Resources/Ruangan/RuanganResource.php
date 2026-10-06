<?php

namespace App\Filament\Resources\Ruangan;

use App\Filament\Resources\Ruangan\Pages\BuatRuangan;
use App\Filament\Resources\Ruangan\Pages\DaftarRuangan;
use App\Filament\Resources\Ruangan\Pages\UbahRuangan;
use App\Filament\Resources\Ruangan\RelationManagers\PicRelationManager;
use App\Models\Ruangan;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RuanganResource extends Resource
{
    protected static ?string $model = Ruangan::class;

    protected static ?string $slug = 'ruangan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?string $modelLabel = 'ruangan';

    protected static ?string $pluralModelLabel = 'Ruangan';

    protected static ?string $navigationLabel = 'Ruangan';

    protected static string|\UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 13;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('kode')->label('Kode')->required()->maxLength(30)->alphaDash()
                ->unique(Ruangan::class, 'kode', ignoreRecord: true),
            TextInput::make('nama')->label('Nama')->required()->maxLength(150),
            Select::make('gedung_id')->label('Gedung')->relationship('gedung', 'nama')->searchable()->preload(),
            Select::make('kategori_ruangan_id')->label('Kategori')->relationship('kategori', 'nama')->searchable()->preload(),
            TextInput::make('lantai')->label('Lantai')->maxLength(10),
            TextInput::make('kapasitas')->label('Kapasitas')->numeric()->minValue(0)->maxValue(65535),
            TextInput::make('luas_m2')->label('Luas (m²)')->numeric()->minValue(0),
            Toggle::make('dapat_dipinjam')->label('Dapat dipinjam (katalog & API Surat)'),
            Select::make('prodi')->label('Dipakai prodi')->relationship('prodi', 'nama')->multiple()->preload(),
            CheckboxList::make('k3l')->label('K3L (RG-11)')->options([
                'apar' => 'APAR',
                'p3k' => 'Kotak P3K',
                'jalur_evakuasi' => 'Jalur evakuasi',
            ])->columns(3),
            Textarea::make('keterangan')->label('Keterangan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['gedung', 'kategori'])->withoutGlobalScopes([SoftDeletingScope::class]))
            ->defaultSort('kode')
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('gedung.nama')->label('Gedung')->sortable(),
                TextColumn::make('kategori.nama')->label('Kategori'),
                TextColumn::make('kapasitas')->label('Kapasitas')->toggleable(),
                IconColumn::make('dapat_dipinjam')->label('Dapat dipinjam')->boolean(),
            ])
            ->filters([
                SelectFilter::make('gedung_id')->label('Gedung')->relationship('gedung', 'nama'),
                SelectFilter::make('kategori_ruangan_id')->label('Kategori')->relationship('kategori', 'nama'),
                TernaryFilter::make('dapat_dipinjam')->label('Dapat dipinjam'),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getRelations(): array
    {
        return [PicRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => DaftarRuangan::route('/'),
            'create' => BuatRuangan::route('/create'),
            'edit' => UbahRuangan::route('/{record}/edit'),
        ];
    }
}
