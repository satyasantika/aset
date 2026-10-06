<?php

namespace App\Filament\Resources\TiketPemeliharaan\Pages;

use App\Enums\StatusTiket;
use App\Filament\Resources\TiketPemeliharaan\TiketPemeliharaanResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class DaftarTiket extends ListRecords
{
    protected static string $resource = TiketPemeliharaanResource::class;

    public function getTabs(): array
    {
        $aktif = fn (Builder $query): Builder => $query->whereIn('status', array_map(fn (StatusTiket $s) => $s->value, StatusTiket::yangAktif()));
        $selesai = fn (Builder $query): Builder => $query->whereNotIn('status', array_map(fn (StatusTiket $s) => $s->value, StatusTiket::yangAktif()));

        return [
            'aktif' => Tab::make('Aktif')->modifyQueryUsing($aktif)->badge($aktif(TiketPemeliharaanResource::getEloquentQuery())->count()),
            'selesai' => Tab::make('Ditutup')->modifyQueryUsing($selesai),
            'semua' => Tab::make('Semua'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'aktif';
    }
}
