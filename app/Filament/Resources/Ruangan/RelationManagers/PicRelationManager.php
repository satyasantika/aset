<?php

namespace App\Filament\Resources\Ruangan\RelationManagers;

use App\Models\Ruangan;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Penugasan PIC ruangan (BR-05): hanya pengguna ber-peran pic-ruangan; satu PIC utama per ruangan. */
class PicRelationManager extends RelationManager
{
    protected static string $relationship = 'pic';

    protected static ?string $inverseRelationship = 'ruanganDikelola';

    protected static ?string $title = 'PIC ruangan';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('email')->label('Surel'),
                IconColumn::make('utama')->label('PIC utama')->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Tugaskan PIC')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'pic-ruangan'))->where('aktif', true))
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Toggle::make('utama')->label('Jadikan PIC utama'),
                    ])
                    ->after(function (array $data) {
                        if (! empty($data['utama']) && ! empty($data['recordId'])) {
                            /** @var Ruangan $ruangan */
                            $ruangan = $this->getOwnerRecord();
                            $ruangan->tetapkanPicUtama(User::query()->findOrFail($data['recordId']));
                        }
                    }),
            ])
            ->recordActions([
                Action::make('jadikanUtama')->label('Jadikan utama')->icon('heroicon-o-star')
                    ->visible(fn (User $record) => ! data_get($record, 'pivot.utama'))
                    ->action(function (User $record) {
                        /** @var Ruangan $ruangan */
                        $ruangan = $this->getOwnerRecord();
                        $ruangan->tetapkanPicUtama($record);
                    }),
                DetachAction::make()->label('Cabut'),
            ]);
    }
}
