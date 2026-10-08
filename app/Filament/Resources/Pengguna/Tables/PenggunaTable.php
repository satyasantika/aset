<?php

namespace App\Filament\Resources\Pengguna\Tables;

use App\Models\Role;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use STS\FilamentImpersonate\Actions\Impersonate;

class PenggunaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Surel')->searchable(),
                TextColumn::make('nip')->label('NIP')->searchable()->toggleable(),
                TextColumn::make('roles.name')->label('Peran')->badge(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('peran')->label('Peran')
                    ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'name')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->whereHas('roles', fn (Builder $q) => $q->where('name', $data['value'])) : $query),
                TernaryFilter::make('aktif')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                // Visibilitas (hanya super-admin, tidak dapat menyasar super-admin lain) ditegakkan oleh
                // User::canImpersonate()/canBeImpersonated(), dicek otomatis oleh paket ini — lihat app/Models/User.php.
                Impersonate::make()
                    ->label('Masuk sebagai')
                    ->redirectTo(fn (User $record): string => $record->hasRole('civitas') && ! $record->roles()->where('name', '!=', 'civitas')->exists()
                        ? route('pinjam')
                        : '/admin'),
            ]);
    }
}
