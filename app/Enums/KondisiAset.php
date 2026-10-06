<?php

namespace App\Enums;

enum KondisiAset: string
{
    case Baik = 'B';
    case RusakRingan = 'RR';
    case RusakBerat = 'RB';

    public function label(): string
    {
        return match ($this) {
            self::Baik => 'Baik',
            self::RusakRingan => 'Rusak Ringan',
            self::RusakBerat => 'Rusak Berat',
        };
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $k) => [$k->value => $k->label()])->all();
    }
}
