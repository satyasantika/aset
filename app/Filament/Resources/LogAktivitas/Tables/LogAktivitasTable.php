<?php

namespace App\Filament\Resources\LogAktivitas\Tables;

use App\Models\Aktivitas;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LogAktivitasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d F Y H:i:s')->sortable(),
                TextColumn::make('log_name')->label('Modul')->badge()->sortable(),
                TextColumn::make('description')->label('Aksi')->searchable(),
                TextColumn::make('subject_type')->label('Objek')->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                TextColumn::make('causer.name')->label('Pelaku')->placeholder('Sistem'),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Modul')
                    ->options(fn () => Aktivitas::query()->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name', 'log_name')->all()),
                SelectFilter::make('causer_id')->label('Pelaku')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Filter::make('tanggal')->label('Tanggal')
                    ->schema([DatePicker::make('dari')->label('Dari'), DatePicker::make('sampai')->label('Sampai')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v))),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
