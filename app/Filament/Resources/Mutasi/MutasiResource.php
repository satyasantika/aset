<?php

namespace App\Filament\Resources\Mutasi;

use App\Actions\Mutasi\AjukanMutasi;
use App\Enums\StatusAset;
use App\Enums\StatusMutasi;
use App\Filament\Resources\Mutasi\Pages\DaftarMutasi;
use App\Filament\Resources\Mutasi\Pages\LihatMutasi;
use App\Filament\Resources\Mutasi\RelationManagers\AsetMutasiRelationManager;
use App\Models\Aset;
use App\Models\Mutasi;
use App\Models\Ruangan;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MutasiResource extends Resource
{
    protected static ?string $model = Mutasi::class;

    protected static ?string $slug = 'mutasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'mutasi';

    protected static ?string $pluralModelLabel = 'Mutasi lokasi';

    protected static ?string $navigationLabel = 'Mutasi lokasi';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        /** @var Builder<Mutasi> $query */
        $query = parent::getEloquentQuery();

        /** @var Builder<Model> $terbatas */
        $terbatas = $query->terlihatOleh($pengguna);

        return $terbatas;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('nomor')->label('Nomor'),
            TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (StatusMutasi $state) => $state->label()),
            TextEntry::make('asal.nama')->label('Ruangan asal'),
            TextEntry::make('tujuan.nama')->label('Ruangan tujuan'),
            TextEntry::make('alasan')->label('Alasan')->columnSpanFull(),
            TextEntry::make('pengaju.name')->label('Diajukan oleh'),
            TextEntry::make('created_at')->label('Diajukan pada')->dateTime('d F Y H:i'),
            TextEntry::make('pemutus.name')->label('Diputuskan oleh')->placeholder('—'),
            TextEntry::make('diputuskan_pada')->label('Diputuskan pada')->dateTime('d F Y H:i')->placeholder('—'),
            TextEntry::make('catatan_keputusan')->label('Catatan keputusan')->placeholder('—')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asal', 'tujuan', 'pengaju'])->withCount('aset'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nomor')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('asal.nama')->label('Dari'),
                TextColumn::make('tujuan.nama')->label('Ke'),
                TextColumn::make('aset_count')->label('Jumlah aset'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (StatusMutasi $state) => $state->label())
                    ->color(fn (StatusMutasi $state) => match ($state) {
                        StatusMutasi::Disetujui => 'success', StatusMutasi::Ditolak => 'danger', StatusMutasi::Diajukan => 'warning', default => 'gray',
                    }),
                TextColumn::make('pengaju.name')->label('Pengaju')->toggleable(),
                TextColumn::make('created_at')->label('Diajukan')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->label('Status')->options(StatusMutasi::opsi())])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [AsetMutasiRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => DaftarMutasi::route('/'),
            'view' => LihatMutasi::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // pengajuan lewat aksi "Ajukan mutasi"
    }

    /** @return array<string, string> Ruangan yang boleh menjadi asal bagi pengguna (BR-05). */
    public static function opsiRuanganAsal(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return Ruangan::query()->dikelolaOleh($pengguna)->orderBy('nama')->pluck('nama', 'id')->all();
    }

    /** @return array<string, string> */
    public static function opsiAsetDi(?string $ruanganId): array
    {
        if ($ruanganId === null) {
            return [];
        }

        return Aset::query()->diRuangan($ruanganId)->where('status', StatusAset::Aktif->value)->orderBy('nama')->limit(500)->get()
            ->mapWithKeys(fn (Aset $a) => [$a->id => "{$a->nama} — {$a->kode_tampil}"])->all();
    }

    /** Pengajuan dari daftar mutasi: pilih ruangan asal, aset, tujuan, alasan. */
    public static function aksiAjukan(): Action
    {
        return Action::make('ajukanMutasi')
            ->label('Ajukan mutasi')->icon('heroicon-o-arrows-right-left')
            ->visible(fn (): bool => auth()->user()?->can('create', Mutasi::class) ?? false)
            ->schema([
                Select::make('ruangan_asal_id')->label('Ruangan asal')->options(fn () => self::opsiRuanganAsal())->required()->live()->searchable(),
                Select::make('aset')->label('Aset yang dipindahkan')->multiple()->required()->searchable()
                    ->options(fn (Get $get) => self::opsiAsetDi($get('ruangan_asal_id'))),
                Select::make('ruangan_tujuan_id')->label('Ruangan tujuan')->required()->searchable()
                    ->options(fn (Get $get) => Ruangan::query()->where('id', '!=', $get('ruangan_asal_id') ?? '')->orderBy('nama')->pluck('nama', 'id')->all()),
                Textarea::make('alasan')->label('Alasan')->required()->maxLength(1000),
            ])
            ->action(function (array $data): void {
                $mutasi = app(AjukanMutasi::class)->handle(
                    Ruangan::query()->findOrFail($data['ruangan_asal_id']),
                    Ruangan::query()->findOrFail($data['ruangan_tujuan_id']),
                    $data['aset'], $data['alasan'], auth()->user(),
                );
                Notification::make()->success()->title("Mutasi {$mutasi->nomor} diajukan")->send();
            });
    }

    /** Pengajuan untuk satu aset dari halaman aset. */
    public static function aksiAjukanUntukAset(): Action
    {
        return Action::make('ajukanMutasi')
            ->label('Ajukan mutasi')->icon('heroicon-o-arrows-right-left')
            ->visible(fn (Aset $record): bool => $record->ruangan !== null
                && (auth()->user()?->can('ajukan', [Mutasi::class, $record->ruangan]) ?? false)
                && $record->status === StatusAset::Aktif)
            ->schema(fn (Aset $record): array => self::skemaTujuan($record->ruangan_id))
            ->action(function (Aset $record, array $data): void {
                $mutasi = app(AjukanMutasi::class)->handle(
                    $record->ruangan, Ruangan::query()->findOrFail($data['ruangan_tujuan_id']), [$record->id], $data['alasan'], auth()->user(),
                );
                Notification::make()->success()->title("Mutasi {$mutasi->nomor} diajukan")->send();
            });
    }

    /** Pengajuan massal dari tabel aset (semua aset terpilih harus berasal dari satu ruangan). */
    public static function aksiAjukanMassal(): BulkAction
    {
        return BulkAction::make('ajukanMutasiMassal')
            ->label('Ajukan mutasi')->icon('heroicon-o-arrows-right-left')
            ->deselectRecordsAfterCompletion()
            ->schema(function (Collection $records): array {
                /** @var Aset|null $pertama */
                $pertama = $records->first();

                return self::skemaTujuan($pertama?->ruangan_id);
            })
            ->action(function (Collection $records, array $data): void {
                $asalId = $records->pluck('ruangan_id')->unique();

                if ($asalId->count() !== 1 || $asalId->first() === null) {
                    Notification::make()->danger()->title('Pilih aset dari satu ruangan yang sama')->send();

                    return;
                }

                $mutasi = app(AjukanMutasi::class)->handle(
                    Ruangan::query()->findOrFail($asalId->first()), Ruangan::query()->findOrFail($data['ruangan_tujuan_id']),
                    $records->pluck('id'), $data['alasan'], auth()->user(),
                );
                Notification::make()->success()->title("Mutasi {$mutasi->nomor} diajukan")->send();
            });
    }

    /** @return array<int, mixed> */
    private static function skemaTujuan(?string $asalId): array
    {
        return [
            Select::make('ruangan_tujuan_id')->label('Ruangan tujuan')->required()->searchable()
                ->options(fn () => Ruangan::query()->where('id', '!=', $asalId ?? '')->orderBy('nama')->pluck('nama', 'id')->all()),
            Textarea::make('alasan')->label('Alasan')->required()->maxLength(1000),
        ];
    }
}
