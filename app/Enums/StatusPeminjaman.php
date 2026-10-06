<?php

namespace App\Enums;

enum StatusPeminjaman: string
{
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';
    case Dipinjam = 'dipinjam';
    case Dikembalikan = 'dikembalikan';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
            self::Dipinjam => 'Dipinjam',
            self::Dikembalikan => 'Dikembalikan',
        };
    }

    /** Status yang mengunci aset pada rentang waktunya (BR-08): disetujui & sedang dipinjam. */
    public function mengunciAset(): bool
    {
        return in_array($this, [self::Disetujui, self::Dipinjam], true);
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
