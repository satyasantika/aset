<?php

namespace App\Filament\Resources\Penghapusan;

use App\Actions\Penghapusan\BuatUsulanPenghapusan;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusUsulanHapus;
use App\Filament\RelationManagers\TautanBerkasRelationManager;
use App\Filament\Resources\Penghapusan\Pages\DaftarUsulan;
use App\Filament\Resources\Penghapusan\Pages\LihatUsulan;
use App\Filament\Resources\Penghapusan\RelationManagers\ItemUsulanRelationManager;
use App\Models\Aset;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class UsulanPenghapusanResource extends Resource
{
    protected static ?string $model = UsulanPenghapusan::class;

    protected static ?string $slug = 'usulan-penghapusan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxXMark;

    protected static ?string $modelLabel = 'usulan penghapusan';

    protected static ?string $pluralModelLabel = 'Usulan penghapusan';

    protected static ?string $navigationLabel = 'Penghapusan';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'nomor';

    public static function getEloquentQuery(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        /** @var Builder<UsulanPenghapusan> $query */
        $query = parent::getEloquentQuery();

        if (! $pengguna->can('penghapusan.kelola')) {
            $query->where('status', '!=', StatusUsulanHapus::Draf->value);   // pejabat tidak melihat draf
        }

        /** @var Builder<Model> $terbatas */
        $terbatas = $query;

        return $terbatas;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('nomor')->label('Nomor'),
            TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (StatusUsulanHapus $state) => $state->label()),
            TextEntry::make('pengusul.name')->label('Pengusul'),
            TextEntry::make('alasan')->label('Alasan')->columnSpanFull(),
            TextEntry::make('pemutus.name')->label('Diputuskan oleh')->placeholder('—'),
            TextEntry::make('diputuskan_pada')->label('Diputuskan pada')->dateTime('d F Y H:i')->placeholder('—'),
            TextEntry::make('catatan_keputusan')->label('Catatan keputusan')->placeholder('—')->columnSpanFull(),
            TextEntry::make('nomor_sk')->label('Nomor SK')->placeholder('—'),
            TextEntry::make('tanggal_sk')->label('Tanggal SK')->date('d F Y')->placeholder('—'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('pengusul')->withCount('item'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nomor')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('item_count')->label('Jumlah aset'),
                TextColumn::make('pengusul.name')->label('Pengusul')->toggleable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (StatusUsulanHapus $state) => $state->label())
                    ->color(fn (StatusUsulanHapus $state) => match ($state) {
                        StatusUsulanHapus::Draf => 'gray', StatusUsulanHapus::Diajukan => 'warning', StatusUsulanHapus::DisetujuiInternal => 'info',
                        StatusUsulanHapus::SkTerbit => 'success', StatusUsulanHapus::Dibatalkan => 'danger',
                    }),
                TextColumn::make('nomor_sk')->label('No. SK')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->label('Status')->options(StatusUsulanHapus::opsi())])
            ->recordActions([ViewAction::make()]);
    }

    public static function getRelations(): array
    {
        return [ItemUsulanRelationManager::class, TautanBerkasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => DaftarUsulan::route('/'),
            'view' => LihatUsulan::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Aset yang layak diusulkan: Rusak Berat aktif atau hilang, dan belum ada dalam usulan terbuka.
     *
     * @return array<string, string>
     */
    public static function opsiAsetLayak(?string $cari = null): array
    {
        return Aset::query()
            ->where(fn (Builder $q) => $q->where('status', StatusAset::Hilang->value)
                ->orWhere(fn (Builder $w) => $w->where('status', StatusAset::Aktif->value)->where('kondisi', KondisiAset::RusakBerat->value)))
            ->whereNotIn('id', fn ($sub) => $sub->select('usulan_penghapusan_item.aset_id')->from('usulan_penghapusan_item')
                ->join('usulan_penghapusan', 'usulan_penghapusan.id', '=', 'usulan_penghapusan_item.usulan_id')
                ->whereIn('usulan_penghapusan.status', ['draf', 'diajukan', 'disetujui_internal']))
            ->when($cari, fn (Builder $q) => $q->where('nama', 'like', "%{$cari}%"))
            ->orderBy('nama')->limit(100)->get()
            ->mapWithKeys(fn (Aset $a) => [$a->id => "{$a->nama} — {$a->kode_tampil} ({$a->status->label()}/{$a->kondisi->value})"])->all();
    }

    public static function aksiBuat(): Action
    {
        return Action::make('buatUsulan')
            ->label('Buat usulan')->icon('heroicon-o-plus')
            ->visible(fn (): bool => auth()->user()?->can('create', UsulanPenghapusan::class) ?? false)
            ->schema([
                Select::make('aset')->label('Aset Rusak Berat / hilang')->multiple()->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => self::opsiAsetLayak($search))
                    ->options(fn (): array => self::opsiAsetLayak()),
                Textarea::make('alasan')->label('Alasan usulan')->required()->maxLength(2000),
            ])
            ->action(function (array $data): void {
                /** @var User $pelaku */
                $pelaku = auth()->user();
                $usulan = app(BuatUsulanPenghapusan::class)->handle($data['aset'], $data['alasan'], $pelaku);
                Notification::make()->success()->title("Usulan {$usulan->nomor} dibuat (draf)")->send();
            });
    }
}
