<?php

namespace App\Filament\Resources\Inventarisasi;

use App\Enums\JenisInventarisasi;
use App\Enums\StatusPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\Pages\BuatPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\Pages\DaftarPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\Pages\LihatPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\RelationManagers\RuanganInventarisasiRelationManager;
use App\Models\PeriodeInventarisasi;
use App\Models\Ruangan;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PeriodeInventarisasiResource extends Resource
{
    protected static ?string $model = PeriodeInventarisasi::class;

    protected static ?string $slug = 'inventarisasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'periode inventarisasi';

    protected static ?string $pluralModelLabel = 'Inventarisasi';

    protected static ?string $navigationLabel = 'Inventarisasi';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        /** @var Builder<PeriodeInventarisasi> $query */
        $query = parent::getEloquentQuery();

        if (! $pengguna->hasAnyRole(['super-admin', 'admin-bmn', 'pejabat-penatausahaan'])) {
            $query->whereIn('periode_inventarisasi.id', DB::table('inventarisasi_ruangan')
                ->where(fn ($q) => $q
                    ->whereIn('ruangan_id', DB::table('ruangan_pic')->where('user_id', $pengguna->getKey())->select('ruangan_id'))
                    ->orWhereIn('id', DB::table('inventarisasi_petugas')->where('user_id', $pengguna->getKey())->select('inventarisasi_ruangan_id')))
                ->select('periode_id'));
        }

        /** @var Builder<Model> $terbatas */
        $terbatas = $query;

        return $terbatas;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->label('Nama periode')->required()->maxLength(150)->placeholder('Inventarisasi 2026'),
            Select::make('jenis')->label('Jenis')->required()->options(collect(JenisInventarisasi::cases())->mapWithKeys(fn ($j) => [$j->value => $j->label()])->all())->default('sensus'),
            DatePicker::make('mulai')->label('Mulai')->required(),
            DatePicker::make('selesai_rencana')->label('Selesai (rencana)')->afterOrEqual('mulai'),
            Select::make('ruangan')->label('Ruangan yang diinventarisasi')->multiple()->required()->searchable()
                ->options(fn () => Ruangan::query()->orderBy('nama')->pluck('nama', 'id')->all()),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('nama')->label('Nama'),
            TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (StatusPeriodeInventarisasi $state) => $state->label()),
            TextEntry::make('jenis')->label('Jenis')->formatStateUsing(fn (JenisInventarisasi $state) => $state->label()),
            TextEntry::make('mulai')->label('Mulai')->date('d F Y'),
            TextEntry::make('selesai_rencana')->label('Selesai (rencana)')->date('d F Y')->placeholder('—'),
            TextEntry::make('dibuka_pada')->label('Dibuka')->dateTime('d F Y H:i')->placeholder('—'),
            TextEntry::make('ditutup_pada')->label('Ditutup')->dateTime('d F Y H:i')->placeholder('—'),
            TextEntry::make('disahkan_pada')->label('Disahkan')->dateTime('d F Y H:i')->placeholder('—'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('ruangan'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('jenis')->label('Jenis')->formatStateUsing(fn (JenisInventarisasi $state) => $state->label()),
                TextColumn::make('mulai')->label('Mulai')->date('d M Y')->sortable(),
                TextColumn::make('ruangan_count')->label('Ruangan'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (StatusPeriodeInventarisasi $state) => $state->label())
                    ->color(fn (StatusPeriodeInventarisasi $state) => match ($state) {
                        StatusPeriodeInventarisasi::Rencana => 'gray', StatusPeriodeInventarisasi::Berjalan => 'info',
                        StatusPeriodeInventarisasi::Ditutup => 'warning', StatusPeriodeInventarisasi::Disahkan => 'success',
                    }),
            ])
            ->filters([SelectFilter::make('status')->label('Status')->options(StatusPeriodeInventarisasi::opsi())])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [RuanganInventarisasiRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => DaftarPeriodeInventarisasi::route('/'),
            'create' => BuatPeriodeInventarisasi::route('/create'),
            'view' => LihatPeriodeInventarisasi::route('/{record}'),
        ];
    }
}
