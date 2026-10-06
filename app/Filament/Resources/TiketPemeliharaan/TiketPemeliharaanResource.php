<?php

namespace App\Filament\Resources\TiketPemeliharaan;

use App\Actions\Pemeliharaan\BukaTiketPemeliharaan;
use App\Enums\StatusTiket;
use App\Filament\Resources\TiketPemeliharaan\Pages\DaftarTiket;
use App\Filament\Resources\TiketPemeliharaan\Pages\LihatTiket;
use App\Models\Aset;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
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
use Illuminate\Support\Facades\Gate;

class TiketPemeliharaanResource extends Resource
{
    protected static ?string $model = TiketPemeliharaan::class;

    protected static ?string $slug = 'tiket-pemeliharaan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $modelLabel = 'tiket pemeliharaan';

    protected static ?string $pluralModelLabel = 'Tiket pemeliharaan';

    protected static ?string $navigationLabel = 'Pemeliharaan';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        /** @var Builder<TiketPemeliharaan> $query */
        $query = parent::getEloquentQuery();

        /** @var Builder<Model> $terbatas */
        $terbatas = $query->terlihatOleh($pengguna);

        return $terbatas;
    }

    public static function infolist(Schema $schema): Schema
    {
        $pelapor = fn (TiketPemeliharaan $r): bool => auth()->user()?->can('lihatDataPelapor', $r) ?? false;

        return $schema->components([
            TextEntry::make('nomor')->label('Nomor'),
            TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (StatusTiket $state) => $state->label()),
            TextEntry::make('aset.nama')->label('Aset'),
            TextEntry::make('aset.kode_tampil')->label('Kode / NUP'),
            TextEntry::make('sumber')->label('Sumber')->badge(),
            TextEntry::make('created_at')->label('Dibuka')->dateTime('d F Y H:i'),
            TextEntry::make('deskripsi')->label('Deskripsi kerusakan')->columnSpanFull(),
            TextEntry::make('nama_pelapor')->label('Pelapor')->placeholder('—')->visible($pelapor),
            TextEntry::make('kontak_pelapor')->label('Kontak pelapor')->placeholder('—')->visible($pelapor),
            TextEntry::make('tindakan')->label('Tindakan')->placeholder('—')->columnSpanFull(),
            TextEntry::make('biaya')->label('Biaya (Rp)')->placeholder('—')->numeric(2, ',', '.'),
            TextEntry::make('selesai_pada')->label('Selesai pada')->dateTime('d F Y H:i')->placeholder('—'),
            TextEntry::make('penangan.name')->label('Ditangani oleh')->placeholder('—'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('aset'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nomor')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('aset.nama')->label('Aset')->searchable(),
                TextColumn::make('deskripsi')->label('Deskripsi')->limit(50)->wrap(),
                TextColumn::make('sumber')->label('Sumber')->badge(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (StatusTiket $state) => $state->label())
                    ->color(fn (StatusTiket $state) => match ($state) {
                        StatusTiket::Baru => 'warning', StatusTiket::Diproses, StatusTiket::MenungguSukuCadang => 'info',
                        StatusTiket::Selesai => 'success', StatusTiket::TidakDapatDiperbaiki => 'danger',
                    }),
                TextColumn::make('created_at')->label('Dibuka')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->label('Status')->options(StatusTiket::opsi())])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => DaftarTiket::route('/'),
            'view' => LihatTiket::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Aksi "Buka tiket" pada baris aset: PIC ruangannya atau admin (Policy `buka`). */
    public static function aksiBukaTiket(): Action
    {
        return Action::make('bukaTiket')
            ->label('Buka tiket perbaikan')->icon('heroicon-o-wrench-screwdriver')
            ->visible(fn (Aset $record): bool => auth()->user()?->can('buka', [TiketPemeliharaan::class, $record]) ?? false)
            ->schema([
                Textarea::make('deskripsi')->label('Deskripsi kerusakan')->required()->maxLength(2000),
                Toggle::make('dalam_perbaikan')->label('Tandai aset "dalam perbaikan" (tidak dapat dipinjam)')->default(true),
            ])
            ->action(function (Aset $record, array $data): void {
                /** @var User $pelaku */
                $pelaku = auth()->user();
                Gate::forUser($pelaku)->authorize('buka', [TiketPemeliharaan::class, $record]);

                $tiket = app(BukaTiketPemeliharaan::class)->handle($record, 'pic', null, $data['deskripsi'], $pelaku, (bool) ($data['dalam_perbaikan'] ?? false));
                Notification::make()->success()->title("Tiket {$tiket->nomor} dibuka")->send();
            });
    }
}
