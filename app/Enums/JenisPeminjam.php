<?php

namespace App\Enums;

enum JenisPeminjam: string
{
    case Civitas = 'civitas';
    case PihakLuar = 'pihak_luar';

    public function label(): string
    {
        return match ($this) {
            self::Civitas => 'Civitas FKIP',
            self::PihakLuar => 'Pihak luar',
        };
    }
}
