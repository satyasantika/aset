<?php

namespace App\Filament\Resources\Dbr;

use App\Actions\Dbr\BangkitkanDbr;
use App\Enums\StatusDbr;
use App\Filament\Resources\Dbr\Pages\DaftarDbr;
use App\Filament\Resources\Dbr\Pages\LihatDbr;
use App\Models\DbrVersi;
use App\Models\Ruangan;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
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

class DbrResource extends Resource
{
    protected static ?string $model = DbrVersi::class;

    protected static ?string $slug = 'dbr';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'DBR/DBL';

    protected static ?string $pluralModelLabel = 'DBR / DBL';

    protected static ?string $navigationLabel = 'DBR / DBL';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        /** @var Builder<DbrVersi> $query */
        $query = parent::getEloquentQuery();

        // pimpinan hanya melihat versi yang pernah disahkan
        if (! ($pengguna->can('dbr.bangkitkan') || $pengguna->can('dbr.sahkan'))) {
            $query->whereIn('status', [StatusDbr::Disahkan->value, StatusDbr::PerluDiperbarui->value]);
        }

        /** @var Builder<Model> $terbatas */
        $terbatas = $query->terlihatOleh($pengguna);

        return $terbatas;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('judul')->label('Dokumen')->state(fn (DbrVersi $r) => $r->judul()),
            TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (StatusDbr $state) => $state->label()),
            TextEntry::make('ringkasan')->label('Jumlah barang')->state(fn (DbrVersi $r) => ($r->snapshot['ringkasan']['jumlah'] ?? 0).' (B '.($r->snapshot['ringkasan']['B'] ?? 0).', RR '.($r->snapshot['ringkasan']['RR'] ?? 0).', RB '.($r->snapshot['ringkasan']['RB'] ?? 0).')'),
            TextEntry::make('snapshot.dibangkitkan_pada')->label('Dibangkitkan')->dateTime('d F Y H:i'),
            TextEntry::make('penyetujuPic.name')->label('Disetujui PIC')->placeholder('—'),
            TextEntry::make('pengesah.name')->label('Disahkan oleh')->placeholder('—'),
            TextEntry::make('catatan')->label('Catatan')->placeholder('—')->columnSpanFull(),
            RepeatableEntry::make('snapshot.aset')->label('Isi dokumen (snapshot)')->columnSpanFull()->columns(5)->schema([
                TextEntry::make('kode_barang')->label('Kode')->placeholder('—'),
                TextEntry::make('nup')->label('NUP')->placeholder('—'),
                TextEntry::make('nama')->label('Nama'),
                TextEntry::make('merk_tipe')->label('Merk/tipe')->placeholder('—'),
                TextEntry::make('kondisi')->label('Kondisi'),
            ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['ruangan']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('jenis')->label('Jenis')->badge()->formatStateUsing(fn (string $state) => strtoupper($state)),
                TextColumn::make('ruangan.nama')->label('Ruangan')->placeholder('Barang lainnya (DBL)')->searchable(),
                TextColumn::make('versi')->label('Versi')->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (StatusDbr $state) => $state->label())
                    ->color(fn (StatusDbr $state) => match ($state) {
                        StatusDbr::Draf => 'gray', StatusDbr::DisetujuiPic => 'info', StatusDbr::Disahkan => 'success', StatusDbr::PerluDiperbarui => 'warning',
                    }),
                TextColumn::make('disahkan_pada')->label('Disahkan')->dateTime('d M Y')->placeholder('—'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(StatusDbr::opsi()),
                SelectFilter::make('ruangan_id')->label('Ruangan')->relationship('ruangan', 'nama'),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => DaftarDbr::route('/'),
            'view' => LihatDbr::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** "Bangkitkan DBR": ruangan (PIC: yang ditugaskan) atau DBL. */
    public static function aksiBangkitkan(): Action
    {
        return Action::make('bangkitkan')
            ->label('Bangkitkan DBR/DBL')->icon('heroicon-o-document-plus')
            ->visible(fn (): bool => auth()->user()?->can('create', DbrVersi::class) ?? false)
            ->schema([
                Select::make('ruangan_id')->label('Ruangan')->required()->searchable()
                    ->options(function (): array {
                        /** @var User $pengguna */
                        $pengguna = auth()->user();
                        $opsi = Ruangan::query()->dikelolaOleh($pengguna)->orderBy('nama')->pluck('nama', 'id')->all();

                        return $pengguna->hasAnyRole(Ruangan::PERAN_SEMUA_RUANGAN) ? ['dbl' => 'Barang lainnya (DBL — tanpa ruangan)'] + $opsi : $opsi;
                    }),
            ])
            ->action(function (array $data): void {
                /** @var User $pelaku */
                $pelaku = auth()->user();
                $ruangan = $data['ruangan_id'] === 'dbl' ? null : Ruangan::query()->findOrFail($data['ruangan_id']);
                $dbr = app(BangkitkanDbr::class)->handle($ruangan, $pelaku);

                Notification::make()->success()->title("{$dbr->judul()} dibangkitkan (draf)")->send();
            });
    }
}
