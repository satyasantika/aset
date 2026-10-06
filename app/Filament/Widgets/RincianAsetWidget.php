<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\Dasbor;
use Filament\Widgets\Widget;

/** Rincian per ruangan dan per kategori (jumlah & nilai) dari statistik dasbor yang di-cache. */
class RincianAsetWidget extends Widget
{
    protected string $view = 'filament.widgets.rincian-aset';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -4;

    public static function canView(): bool
    {
        return auth()->user()?->can('aset.lihat') ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return ['s' => Dasbor::statistik($pengguna)];
    }
}
