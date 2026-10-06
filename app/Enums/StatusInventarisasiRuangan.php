<?php

namespace App\Enums;

enum StatusInventarisasiRuangan: string
{
    case Belum = 'belum';
    case Berjalan = 'berjalan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum dimulai',
            self::Berjalan => 'Berjalan',
            self::Selesai => 'Selesai',
        };
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
