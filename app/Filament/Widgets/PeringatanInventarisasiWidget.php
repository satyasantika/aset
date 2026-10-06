<?php

namespace App\Filament\Widgets;

use App\Support\PeringatanInventarisasi;
use Filament\Widgets\Widget;

/** BR-15: peringatan inventarisasi terakhir yang disahkan melewati ambang tahun. Hanya untuk pengelola, pejabat, pimpinan. */
class PeringatanInventarisasiWidget extends Widget
{
    protected string $view = 'filament.widgets.peringatan-inventarisasi';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'admin-bmn', 'pejabat-penatausahaan', 'pimpinan']) ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['peringatan' => PeringatanInventarisasi::cek()];
    }
}
