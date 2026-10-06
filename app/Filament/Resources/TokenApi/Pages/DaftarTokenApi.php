<?php

namespace App\Filament\Resources\TokenApi\Pages;

use App\Actions\Api\BuatTokenKlien;
use App\Filament\Resources\TokenApi\TokenApiResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class DaftarTokenApi extends ListRecords
{
    protected static string $resource = TokenApiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buatToken')
                ->label('Buat token')
                ->icon('heroicon-o-plus')
                ->schema([
                    TextInput::make('nama')->label('Nama klien')->required()->maxLength(100),
                    CheckboxList::make('abilities')->label('Ability')->required()->options([
                        'ruangan:baca' => 'ruangan:baca — membaca ruangan & jadwal',
                        'ruangan:pakai' => 'ruangan:pakai — mencatat pemakaian ruangan',
                    ])->default(['ruangan:baca']),
                ])
                ->action(function (array $data): void {
                    /** @var User $pelaku */
                    $pelaku = auth()->user();
                    $teks = app(BuatTokenKlien::class)->handle($data['nama'], $data['abilities'], $pelaku);

                    Notification::make()->success()->persistent()->title('Token dibuat — salin sekarang')
                        ->body("Token hanya ditampilkan sekali:\n{$teks}")->send();
                }),
        ];
    }
}
