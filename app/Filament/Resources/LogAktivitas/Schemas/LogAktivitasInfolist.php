<?php

namespace App\Filament\Resources\LogAktivitas\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LogAktivitasInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('Waktu')->dateTime('d F Y H:i:s'),
            TextEntry::make('log_name')->label('Modul')->badge(),
            TextEntry::make('description')->label('Aksi'),
            TextEntry::make('causer.name')->label('Pelaku')->placeholder('Sistem'),
            TextEntry::make('subject_type')->label('Objek')->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
            TextEntry::make('subject_id')->label('ID objek'),
            KeyValueEntry::make('properties.attributes')->label('Nilai baru')->placeholder('—'),
            KeyValueEntry::make('properties.old')->label('Nilai lama')->placeholder('—'),
        ]);
    }
}
