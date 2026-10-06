<?php

namespace App\Enums;

enum StatusUsulanHapus: string
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case DisetujuiInternal = 'disetujui_internal';
    case SkTerbit = 'sk_terbit';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::DisetujuiInternal => 'Disetujui internal',
            self::SkTerbit => 'SK terbit',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** Masih berjalan (mengunci aset agar tidak masuk usulan lain). */
    public function terbuka(): bool
    {
        return in_array($this, [self::Draf, self::Diajukan, self::DisetujuiInternal], true);
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
