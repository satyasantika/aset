<?php

namespace App\Filament\Resources\Aset\Pages;

use App\Actions\Aset\DaftarkanAsetMassal;
use App\Filament\Resources\Aset\AsetResource;
use App\Models\Aset;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class DaftarAset extends ListRecords
{
    protected static string $resource = AsetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('daftarkanMassal')
                ->label('Daftarkan massal')
                ->icon('heroicon-o-squares-plus')
                ->visible(fn () => auth()->user()?->can('create', Aset::class) ?? false)
                ->modalWidth('5xl')
                ->schema([
                    ...AsetResource::komponenData(massal: true),
                    TextInput::make('jumlah')->label('Jumlah unit')->numeric()->required()->minValue(1)->maxValue(DaftarkanAsetMassal::MAKS_UNIT),
                    TextInput::make('nup_awal')->label('NUP awal (kosong = lanjutkan NUP terakhir)')->numeric()->minValue(1)
                        ->visible(fn (Get $get) => $get('status_bmn') !== 'belum_tercatat'),
                    TextInput::make('awalan_kode_internal')->label('Awalan kode internal')->maxLength(24)
                        ->visible(fn (Get $get) => $get('status_bmn') === 'belum_tercatat')
                        ->required(fn (Get $get) => $get('status_bmn') === 'belum_tercatat'),
                ])
                ->action(function (array $data) {
                    /** @var User $pelaku */
                    $pelaku = auth()->user();
                    $jumlah = (int) $data['jumlah'];
                    $nupAwal = filled($data['nup_awal'] ?? null) ? (int) $data['nup_awal'] : null;
                    $prefix = $data['awalan_kode_internal'] ?? null;
                    unset($data['jumlah'], $data['nup_awal'], $data['awalan_kode_internal']);

                    $hasil = app(DaftarkanAsetMassal::class)->handle($data, $jumlah, $nupAwal, $pelaku, $prefix);

                    Notification::make()->success()->title("{$hasil->count()} aset didaftarkan")->send();
                }),
            AsetResource::aksiCetakLabel(),
            CreateAction::make(),
        ];
    }
}
