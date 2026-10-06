<?php

namespace App\Filament\Resources\Aset;

use App\Actions\Aset\UbahKondisiAset;
use App\Actions\Aset\UbahStatusAset;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusBmn;
use App\Enums\SumberPerolehan;
use App\Filament\RelationManagers\TautanBerkasRelationManager;
use App\Filament\Resources\Aset\Pages\BuatAset;
use App\Filament\Resources\Aset\Pages\DaftarAset;
use App\Filament\Resources\Aset\Pages\LabelPerluCetakUlang;
use App\Filament\Resources\Aset\Pages\UbahAset;
use App\Filament\Resources\Aset\RelationManagers\RiwayatKondisiRelationManager;
use App\Filament\Resources\Aset\RelationManagers\RiwayatLokasiRelationManager;
use App\Filament\Resources\Aset\RelationManagers\RiwayatStatusRelationManager;
use App\Filament\Resources\Mutasi\MutasiResource;
use App\Models\Aset;
use App\Models\KategoriRuangan;
use App\Models\KodefikasiBarang;
use App\Models\Ruangan;
use App\Rules\KodeBarangValid;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AsetResource extends Resource
{
    protected static ?string $model = Aset::class;

    protected static ?string $slug = 'aset';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $modelLabel = 'aset';

    protected static ?string $pluralModelLabel = 'Aset';

    protected static ?string $navigationLabel = 'Aset';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nama';

    /**
     * Bagian formulir yang dipakai bersama oleh pendaftaran tunggal, massal, dan ubah.
     *
     * @return array<int, Section>
     */
    public static function komponenData(bool $identitasDapatDiubah = true, bool $massal = false): array
    {
        return [
            Section::make('Identitas barang')->columns(2)->schema([
                Select::make('status_bmn')->label('Status BMN')->required()->options(StatusBmn::opsi())
                    ->default(StatusBmn::Tercatat->value)->live()->disabled(! $identitasDapatDiubah)->dehydrated($identitasDapatDiubah),
                Select::make('kode_barang')->label('Kode barang (kodefikasi)')->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => KodefikasiBarang::query()
                        ->where('tingkat', KodefikasiBarang::TINGKAT_SUB_SUB_KELOMPOK)
                        ->where(fn (Builder $q) => $q->where('kode', 'like', "%{$search}%")->orWhere('uraian', 'like', "%{$search}%"))
                        ->limit(50)->get()->mapWithKeys(fn (KodefikasiBarang $k) => [$k->kode => "{$k->kode} — {$k->uraian}"])->all())
                    ->getOptionLabelUsing(fn (?string $value): ?string => $value ? KodefikasiBarang::query()->where('kode', $value)->value('uraian') : null)
                    ->rules(fn (Get $get): array => $get('status_bmn') === StatusBmn::BelumTercatat->value && blank($get('kode_barang')) ? [] : [new KodeBarangValid])
                    ->required(fn (Get $get): bool => $get('status_bmn') !== StatusBmn::BelumTercatat->value)
                    ->disabled(! $identitasDapatDiubah)->dehydrated($identitasDapatDiubah),
                ...($massal ? [] : [
                    TextInput::make('nup')->label('NUP')->numeric()->minValue(1)
                        ->required(fn (Get $get): bool => $get('status_bmn') !== StatusBmn::BelumTercatat->value)
                        ->disabled(! $identitasDapatDiubah)->dehydrated($identitasDapatDiubah),
                ]),
                ...($massal ? [] : [
                    TextInput::make('kode_internal')->label('Kode internal (belum tercatat)')->maxLength(30)
                        ->unique(Aset::class, 'kode_internal', ignoreRecord: true)
                        ->required(fn (Get $get): bool => $get('status_bmn') === StatusBmn::BelumTercatat->value)
                        ->disabled(! $identitasDapatDiubah)->dehydrated($identitasDapatDiubah),
                ]),
                TextInput::make('nama')->label('Nama barang')->required()->maxLength(255),
                TextInput::make('merk_tipe')->label('Merk / tipe')->maxLength(255),
                Textarea::make('spesifikasi')->label('Spesifikasi')->columnSpanFull(),
            ]),
            Section::make('Perolehan (RG-10)')->columns(2)->schema([
                TextInput::make('tahun_perolehan')->label('Tahun perolehan')->numeric()->minValue(1900)->maxValue((int) now()->format('Y') + 1),
                DatePicker::make('tanggal_perolehan')->label('Tanggal perolehan'),
                TextInput::make('nilai_perolehan')->label('Nilai perolehan (Rp)')->numeric()->minValue(0)->step('0.01'),
                Select::make('sumber_perolehan')->label('Sumber perolehan')->options(SumberPerolehan::opsi()),
                TextInput::make('sumber_dana')->label('Sumber dana')->maxLength(50),
                TextInput::make('nomor_dokumen_perolehan')->label('Nomor dokumen perolehan (BAST/kontrak)')->maxLength(100),
                TextInput::make('penguasaan')->label('Penguasaan')->default('milik_sendiri')->maxLength(30),
            ]),
            Section::make('Lokasi & kondisi')->columns(2)->schema([
                Select::make('ruangan_id')->label('Ruangan')->relationship('ruangan', 'nama')->searchable()->preload()
                    ->disabled(! $identitasDapatDiubah)->dehydrated($identitasDapatDiubah),
                TextInput::make('lokasi_lainnya')->label('Lokasi lainnya (DBL; bila tanpa ruangan)')->maxLength(150)
                    ->disabled(! $identitasDapatDiubah)->dehydrated($identitasDapatDiubah),
                Select::make('kondisi')->label('Kondisi')->required()->options(KondisiAset::opsi())->default(KondisiAset::Baik->value)
                    ->disabled(! $identitasDapatDiubah)->dehydrated($identitasDapatDiubah),
                Toggle::make('dapat_dipinjam')->label('Dapat dipinjam'),
                TextInput::make('kelompok_pengadaan')->label('Kelompok pengadaan')->maxLength(50),
                Textarea::make('keterangan')->label('Keterangan')->columnSpanFull(),
            ]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::komponenData());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('ruangan'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('kode_barang')->label('Kode barang')->searchable()->sortable()->placeholder('—'),
                TextColumn::make('nup')->label('NUP')->searchable()->sortable()->placeholder('—'),
                TextColumn::make('kode_internal')->label('Kode internal')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nama')->label('Nama')->searchable()->sortable()->wrap(),
                TextColumn::make('merk_tipe')->label('Merk/tipe')->searchable()->toggleable(),
                TextColumn::make('ruangan.nama')->label('Ruangan')->placeholder('Lokasi lainnya'),
                TextColumn::make('kondisi')->label('Kondisi')->badge()
                    ->formatStateUsing(fn (KondisiAset $state) => $state->value)
                    ->color(fn (KondisiAset $state) => match ($state) {
                        KondisiAset::Baik => 'success', KondisiAset::RusakRingan => 'warning', KondisiAset::RusakBerat => 'danger',
                    }),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (StatusAset $state) => $state->label()),
                IconColumn::make('label_perlu_cetak_ulang')->label('Cetak ulang')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('ruangan_id')->label('Ruangan')->relationship('ruangan', 'nama')->searchable()->preload(),
                SelectFilter::make('kategori')->label('Kategori ruangan')
                    ->options(fn () => KategoriRuangan::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('ruangan', fn (Builder $q) => $q->where('kategori_ruangan_id', $data['value']))
                        : $query),
                SelectFilter::make('kondisi')->label('Kondisi')->options(KondisiAset::opsi()),
                SelectFilter::make('status')->label('Status')->options(StatusAset::opsi()),
                SelectFilter::make('status_bmn')->label('Status BMN')->options(StatusBmn::opsi()),
                TernaryFilter::make('label_perlu_cetak_ulang')->label('Label perlu cetak ulang'),
                Filter::make('belum_dicetak')->label('Label belum pernah dicetak')->query(fn (Builder $query) => $query->whereNull('dicetak_pada')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::aksiUbahKondisi(),
                self::aksiUbahStatus(),
                MutasiResource::aksiAjukanUntukAset(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([self::aksiUbahKondisiMassal(), self::aksiCetakLabelMassal(), MutasiResource::aksiAjukanMassal()]),
            ]);
    }

    public static function aksiUbahKondisi(): Action
    {
        return Action::make('ubahKondisi')
            ->label('Ubah kondisi')->icon('heroicon-o-wrench')
            ->visible(fn (Aset $record): bool => auth()->user()?->can('ubahKondisi', $record) ?? false)
            ->fillForm(fn (Aset $record): array => ['kondisi' => $record->kondisi->value])
            ->schema([
                Select::make('kondisi')->label('Kondisi baru')->options(KondisiAset::opsi())->required(),
                Textarea::make('catatan')->label('Catatan')->maxLength(500),
            ])
            ->action(function (Aset $record, array $data): void {
                app(UbahKondisiAset::class)->handle($record, KondisiAset::from($data['kondisi']), auth()->user(), 'manual', null, $data['catatan'] ?? null);
                Notification::make()->success()->title('Kondisi diperbarui')->send();
            });
    }

    public static function aksiCetakLabel(): Action
    {
        return Action::make('cetakLabel')
            ->label('Cetak label')->icon('heroicon-o-qr-code')
            ->visible(fn (): bool => auth()->user()?->can('label.cetak') ?? false)
            ->schema([
                Select::make('mode')->label('Yang dicetak')->required()->default('belum_dicetak')->options([
                    'belum_dicetak' => 'Semua yang belum pernah dicetak',
                    'perlu_cetak_ulang' => 'Semua yang perlu cetak ulang',
                    'ruangan' => 'Semua aset di satu ruangan',
                ])->live(),
                Select::make('ruangan_id')->label('Ruangan')->searchable()
                    ->options(fn () => Ruangan::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->visible(fn (Get $get) => $get('mode') === 'ruangan')->required(fn (Get $get) => $get('mode') === 'ruangan'),
            ])
            ->action(fn (array $data) => redirect()->route('cetak.label', array_filter($data)));
    }

    public static function aksiCetakLabelMassal(): BulkAction
    {
        return BulkAction::make('cetakLabelMassal')
            ->label('Cetak label')->icon('heroicon-o-qr-code')
            ->visible(fn (): bool => auth()->user()?->can('label.cetak') ?? false)
            ->authorizeIndividualRecords('cetakLabel')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) {
                session()->put('cetak_label_ids', $records->pluck('id')->all());

                return redirect()->route('cetak.label', ['mode' => 'terpilih']);
            });
    }

    public static function aksiUbahKondisiMassal(): BulkAction
    {
        return BulkAction::make('ubahKondisiMassal')
            ->label('Ubah kondisi')->icon('heroicon-o-wrench')
            ->authorizeIndividualRecords('ubahKondisi')
            ->deselectRecordsAfterCompletion()
            ->schema([
                Select::make('kondisi')->label('Kondisi baru')->options(KondisiAset::opsi())->required(),
                Textarea::make('catatan')->label('Catatan')->maxLength(500),
            ])
            ->action(function (Collection $records, array $data): void {
                foreach ($records as $aset) {
                    /** @var Aset $aset */
                    app(UbahKondisiAset::class)->handle($aset, KondisiAset::from($data['kondisi']), auth()->user(), 'manual', null, $data['catatan'] ?? null);
                }
                Notification::make()->success()->title($records->count().' aset diperbarui')->send();
            });
    }

    public static function aksiUbahStatus(): Action
    {
        return Action::make('ubahStatus')
            ->label('Ubah status')->icon('heroicon-o-arrows-right-left')
            ->visible(fn (Aset $record): bool => (auth()->user()?->can('ubahStatus', $record) ?? false) && $record->status->transisiSah() !== [])
            ->schema(fn (Aset $record): array => [
                Select::make('status')->label('Status baru')->required()->live()
                    ->options(collect($record->status->transisiSah())->mapWithKeys(fn (StatusAset $s) => [$s->value => $s->label()])->all()),
                TextInput::make('nomor_sk_penghapusan')->label('Nomor SK penghapusan')->maxLength(100)
                    ->visible(fn (Get $get) => $get('status') === StatusAset::Dihapus->value)
                    ->required(fn (Get $get) => $get('status') === StatusAset::Dihapus->value),
                DatePicker::make('tanggal_sk_penghapusan')->label('Tanggal SK penghapusan')
                    ->visible(fn (Get $get) => $get('status') === StatusAset::Dihapus->value)
                    ->required(fn (Get $get) => $get('status') === StatusAset::Dihapus->value),
                Textarea::make('catatan')->label('Catatan')->maxLength(500),
            ])
            ->action(function (Aset $record, array $data): void {
                app(UbahStatusAset::class)->handle($record, StatusAset::from($data['status']), auth()->user(), $data['catatan'] ?? null, [
                    'nomor_sk_penghapusan' => $data['nomor_sk_penghapusan'] ?? null,
                    'tanggal_sk_penghapusan' => $data['tanggal_sk_penghapusan'] ?? null,
                ]);
                Notification::make()->success()->title('Status diperbarui')->send();
            });
    }

    /** @return array<int, NavigationItem> */
    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
            NavigationItem::make('Label perlu cetak ulang')
                ->icon('heroicon-o-qr-code')->group(null)->sort(5)
                ->url(fn (): string => static::getUrl('label-perlu-cetak-ulang'))
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.label-perlu-cetak-ulang'))
                ->visible(fn (): bool => auth()->user()?->can('label.cetak') ?? false),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RiwayatKondisiRelationManager::class,
            RiwayatLokasiRelationManager::class,
            RiwayatStatusRelationManager::class,
            TautanBerkasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => DaftarAset::route('/'),
            'create' => BuatAset::route('/create'),
            'label-perlu-cetak-ulang' => LabelPerluCetakUlang::route('/label-perlu-cetak-ulang'),
            'view' => Pages\LihatAset::route('/{record}'),
            'edit' => UbahAset::route('/{record}/edit'),
        ];
    }
}
