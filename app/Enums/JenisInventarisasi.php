<?php

namespace App\Enums;

enum JenisInventarisasi: string
{
    case Sensus = 'sensus';
    case OpnameInternal = 'opname_internal';

    public function label(): string
    {
        return match ($this) {
            self::Sensus => 'Sensus barang',
            self::OpnameInternal => 'Opname internal',
        };
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
