<?php

namespace App\Enums;

enum StatusBmn: string
{
    case Tercatat = 'tercatat';
    case BelumTercatat = 'belum_tercatat';

    public function label(): string
    {
        return match ($this) {
            self::Tercatat => 'Tercatat di aplikasi BMN',
            self::BelumTercatat => 'Belum tercatat',
        };
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
