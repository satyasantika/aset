<?php

namespace App\Enums;

enum StatusPeriodeInventarisasi: string
{
    case Rencana = 'rencana';
    case Berjalan = 'berjalan';
    case Ditutup = 'ditutup';
    case Disahkan = 'disahkan';

    public function label(): string
    {
        return match ($this) {
            self::Rencana => 'Rencana',
            self::Berjalan => 'Berjalan',
            self::Ditutup => 'Ditutup',
            self::Disahkan => 'Disahkan',
        };
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
