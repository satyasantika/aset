<?php

namespace App\Filament\Resources\Peminjaman;

use App\Actions\Peminjaman\CatatPermohonanPihakLuar;
use App\Enums\JenisPeminjam;
use App\Enums\StatusPeminjaman;
use App\Filament\Resources\Peminjaman\Pages\DaftarPeminjaman;
use App\Filament\Resources\Peminjaman\Pages\LihatPeminjaman;
use App\Filament\Resources\Peminjaman\RelationManagers\ItemPeminjamanRelationManager;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PeminjamanResource extends Resource
{
    protected static ?string $model = Peminjaman::class;

    protected static ?string $slug = 'peminjaman';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'peminjaman';

    protected static ?string $pluralModelLabel = 'Peminjaman';

    protected static ?string $navigationLabel = 'Peminjaman';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        /** @var Builder<Peminjaman> $query */
        $query = parent::getEloquentQuery();

        /** @var Builder<Model> $terbatas */
        $terbatas = $query->terlihatOleh($pengguna);

        return $terbatas;
    }

    public static function infolist(Schema $schema): Schema
    {
        $pribadi = fn (Peminjaman $r): bool => auth()->user()?->can('lihatDataPribadi', $r) ?? false;

        return $schema->components([
            TextEntry::make('nomor')->label('Nomor'),
            TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (StatusPeminjaman $state) => $state->label()),
            TextEntry::make('jenis_peminjam')->label('Jenis peminjam')->formatStateUsing(fn (JenisPeminjam $state) => $state->label()),
            TextEntry::make('nama_peminjam')->label('Peminjam')->visible($pribadi),
            TextEntry::make('kontak_peminjam')->label('Kontak')->placeholder('—')->visible($pribadi),
            TextEntry::make('unit_peminjam')->label('Unit')->placeholder('—')->visible($pribadi),
            TextEntry::make('keperluan')->label('Keperluan')->columnSpanFull(),
            TextEntry::make('mulai')->label('Mulai')->dateTime('d F Y H:i'),
            TextEntry::make('rencana_kembali')->label('Rencana kembali')->dateTime('d F Y H:i'),
            TextEntry::make('diputuskan_pada')->label('Diputuskan pada')->dateTime('d F Y H:i')->placeholder('—'),
            TextEntry::make('catatan_keputusan')->label('Catatan keputusan')->placeholder('—')->columnSpanFull(),
            TextEntry::make('diserahkan_pada')->label('Diserahkan pada')->dateTime('d F Y H:i')->placeholder('—'),
            TextEntry::make('dikembalikan_pada')->label('Dikembalikan pada')->dateTime('d F Y H:i')->placeholder('—'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        $pribadi = fn (): bool => auth()->user()?->can('data-pribadi.lihat') ?? false;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('item'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nomor')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('nama_peminjam')->label('Peminjam')->searchable()->visible($pribadi),
                TextColumn::make('jenis_peminjam')->label('Jenis')->badge()->formatStateUsing(fn (JenisPeminjam $state) => $state->label()),
                TextColumn::make('item_count')->label('Jumlah aset'),
                TextColumn::make('mulai')->label('Mulai')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('rencana_kembali')->label('Rencana kembali')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (StatusPeminjaman $state) => $state->label())
                    ->color(fn (StatusPeminjaman $state) => match ($state) {
                        StatusPeminjaman::Dipinjam => 'info', StatusPeminjaman::Disetujui => 'success', StatusPeminjaman::Diajukan => 'warning',
                        StatusPeminjaman::Ditolak => 'danger', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(StatusPeminjaman::opsi()),
                SelectFilter::make('jenis_peminjam')->label('Jenis')->options(['civitas' => 'Civitas FKIP', 'pihak_luar' => 'Pihak luar']),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [ItemPeminjamanRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => DaftarPeminjaman::route('/'),
            'view' => LihatPeminjaman::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Admin mencatat permohonan pihak luar untuk diputuskan pejabat-penatausahaan (BR-07). */
    public static function aksiCatatPihakLuar(): Action
    {
        return Action::make('catatPihakLuar')
            ->label('Catat permohonan pihak luar')->icon('heroicon-o-building-office')
            ->visible(fn (): bool => auth()->user()?->can('catatPihakLuar', Peminjaman::class) ?? false)
            ->schema([
                TextInput::make('nama_peminjam')->label('Nama pemohon / instansi')->required()->maxLength(150),
                TextInput::make('kontak_peminjam')->label('Kontak')->maxLength(50),
                TextInput::make('unit_peminjam')->label('Asal instansi/unit')->maxLength(150),
                Select::make('aset')->label('Aset')->multiple()->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Aset::query()->where('nama', 'like', "%{$search}%")->limit(30)->get()
                        ->mapWithKeys(fn (Aset $a) => [$a->id => "{$a->nama} — {$a->kode_tampil}"])->all())
                    ->getOptionLabelsUsing(fn (array $values): array => Aset::query()->whereIn('id', $values)->get()
                        ->mapWithKeys(fn (Aset $a) => [$a->id => "{$a->nama} — {$a->kode_tampil}"])->all()),
                DateTimePicker::make('mulai')->label('Mulai')->required()->seconds(false),
                DateTimePicker::make('rencana_kembali')->label('Selesai')->required()->seconds(false),
                Textarea::make('keperluan')->label('Keperluan')->required()->maxLength(1000),
            ])
            ->action(function (array $data): void {
                $p = app(CatatPermohonanPihakLuar::class)->handle(
                    $data['aset'],
                    ['nama_peminjam' => $data['nama_peminjam'], 'kontak_peminjam' => $data['kontak_peminjam'] ?? null, 'unit_peminjam' => $data['unit_peminjam'] ?? null],
                    $data['keperluan'], Carbon::parse($data['mulai']), Carbon::parse($data['rencana_kembali']), auth()->user(),
                );
                Notification::make()->success()->title("Permohonan {$p->nomor} dicatat dan diteruskan ke pejabat penatausahaan")->send();
            });
    }
}
