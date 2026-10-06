<?php

namespace App\Enums;

enum StatusDbr: string
{
    case Draf = 'draf';
    case DisetujuiPic = 'disetujui_pic';
    case Disahkan = 'disahkan';
    case PerluDiperbarui = 'perlu_diperbarui';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::DisetujuiPic => 'Disetujui PIC',
            self::Disahkan => 'Disahkan',
            self::PerluDiperbarui => 'Perlu diperbarui',
        };
    }

    /** Versi yang masih dalam penyusunan (belum menjadi dokumen sah). */
    public function dalamProses(): bool
    {
        return in_array($this, [self::Draf, self::DisetujuiPic], true);
    }

    /** Versi yang pernah disahkan (isi snapshot adalah dokumen resmi). */
    public function pernahDisahkan(): bool
    {
        return in_array($this, [self::Disahkan, self::PerluDiperbarui], true);
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
