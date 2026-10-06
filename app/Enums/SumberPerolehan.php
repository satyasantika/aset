<?php

namespace App\Enums;

enum SumberPerolehan: string
{
    case Pembelian = 'pembelian';
    case Hibah = 'hibah';
    case TransferMasuk = 'transfer_masuk';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Pembelian => 'Pembelian',
            self::Hibah => 'Hibah',
            self::TransferMasuk => 'Transfer masuk',
            self::Lainnya => 'Lainnya',
        };
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
