<?php

namespace App\Enums;

enum HasilInventarisasi: string
{
    case Ditemukan = 'ditemukan';
    case TidakDitemukan = 'tidak_ditemukan';
    case KondisiBerubah = 'kondisi_berubah';
    case Berlebih = 'berlebih';

    public function label(): string
    {
        return match ($this) {
            self::Ditemukan => 'Ditemukan',
            self::TidakDitemukan => 'Tidak ditemukan',
            self::KondisiBerubah => 'Kondisi berubah',
            self::Berlebih => 'Berlebih (tidak tercatat)',
        };
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
