<?php

namespace App\Enums;

enum StatusTiket: string
{
    case Baru = 'baru';
    case Diproses = 'diproses';
    case MenungguSukuCadang = 'menunggu_suku_cadang';
    case Selesai = 'selesai';
    case TidakDapatDiperbaiki = 'tidak_dapat_diperbaiki';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Diproses => 'Diproses',
            self::MenungguSukuCadang => 'Menunggu suku cadang',
            self::Selesai => 'Selesai',
            self::TidakDapatDiperbaiki => 'Tidak dapat diperbaiki',
        };
    }

    /** Tiket yang masih berjalan. */
    public function aktif(): bool
    {
        return in_array($this, [self::Baru, self::Diproses, self::MenungguSukuCadang], true);
    }

    /** @return list<self> */
    public static function yangAktif(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->aktif()));
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
