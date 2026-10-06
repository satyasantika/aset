<?php

namespace App\Filament\Resources\Inventarisasi\RelationManagers;

use App\Actions\Inventarisasi\TugaskanPetugas;
use App\Enums\StatusInventarisasiRuangan;
use App\Models\InventarisasiRuangan;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Ruangan dalam periode, petugas yang ditugaskan, dan statusnya. */
class RuanganInventarisasiRelationManager extends RelationManager
{
    protected static string $relationship = 'ruangan';

    protected static ?string $title = 'Ruangan & petugas';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['ruangan', 'petugas']))
            ->columns([
                TextColumn::make('ruangan.nama')->label('Ruangan')->searchable(),
                TextColumn::make('petugas.name')->label('Petugas')->badge()->placeholder('Belum ditugaskan'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (StatusInventarisasiRuangan $state) => $state->label()),
                TextColumn::make('selesai_pada')->label('Selesai')->dateTime('d M Y H:i')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('tugaskan')->label('Tugaskan petugas')->icon('heroicon-o-user-plus')
                    ->visible(fn (InventarisasiRuangan $record): bool => (auth()->user()?->can('tugaskan', $record) ?? false) && $record->status !== StatusInventarisasiRuangan::Selesai)
                    ->fillForm(fn (InventarisasiRuangan $record): array => ['petugas' => $record->petugas->pluck('id')->all()])
                    ->schema([
                        Select::make('petugas')->label('Petugas (admin/PIC)')->multiple()->required()->searchable()
                            ->options(fn (): array => User::query()->where('aktif', true)
                                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin-bmn', 'pic-ruangan', 'super-admin']))
                                ->orderBy('name')->pluck('name', 'id')->all()),
                    ])
                    ->action(function (InventarisasiRuangan $record, array $data): void {
                        app(TugaskanPetugas::class)->handle($record, $data['petugas'], auth()->user());
                        Notification::make()->success()->title('Petugas ditugaskan')->send();
                    }),
            ]);
    }
}
