<?php

namespace App\Filament\Resources\Aset\Pages;

use App\Filament\Resources\Aset\AsetResource;
use App\Models\Aset;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Laporan "Label perlu dicetak ulang" per ruangan (07-MIGRASI §4a): aset yang labelnya tidak dapat dipercaya
 * (R-17). PIC hanya melihat ruangannya (BR-05); cetak lewat pencetak label (mode perlu_cetak_ulang).
 */
class LabelPerluCetakUlang extends ListRecords
{
    protected static string $resource = AsetResource::class;

    protected static ?string $title = 'Label perlu dicetak ulang';

    /** Hanya peran yang boleh mencetak label (label.cetak); PIC terbatas ruangannya lewat scope. */
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('label.cetak') ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return AsetResource::table($table)
            ->modifyQueryUsing(function (Builder $query) use ($pengguna): Builder {
                /** @var Builder<Aset> $query */
                return $query->with('ruangan')->where('label_perlu_cetak_ulang', true)->dikelolaOleh($pengguna)->orderBy('ruangan_id')->orderBy('nama');
            })
            ->groups([Group::make('ruangan.nama')->label('Ruangan')->collapsible()])
            ->defaultGroup('ruangan.nama')
            ->heading('Aset dengan label yang harus dicetak ulang dan ditempel pada inventarisasi pertama');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cetakSemua')
                ->label('Cetak semua label ini')->icon('heroicon-o-qr-code')
                ->visible(fn (): bool => auth()->user()?->can('label.cetak') ?? false)
                ->url(fn (): string => route('cetak.label', ['mode' => 'perlu_cetak_ulang'])),
        ];
    }

    public function getSubheading(): ?string
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $jumlah = Aset::query()->where('label_perlu_cetak_ulang', true)->dikelolaOleh($pengguna)->count();

        return "{$jumlah} aset";
    }
}
