<?php

namespace App\Enums;

enum StatusAset: string
{
    case Aktif = 'aktif';
    case DalamPerbaikan = 'dalam_perbaikan';
    case DiusulkanHapus = 'diusulkan_hapus';
    case Hilang = 'hilang';
    case Dihapus = 'dihapus';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::DalamPerbaikan => 'Dalam perbaikan',
            self::DiusulkanHapus => 'Diusulkan hapus',
            self::Hilang => 'Hilang',
            self::Dihapus => 'Dihapus',
        };
    }

    /**
     * Transisi sah menurut diagram status PRD §7.
     *
     * @return list<self>
     */
    public function transisiSah(): array
    {
        return match ($this) {
            self::Aktif => [self::DalamPerbaikan, self::Hilang, self::DiusulkanHapus],
            self::DalamPerbaikan => [self::Aktif],
            self::Hilang => [self::Aktif, self::DiusulkanHapus],
            self::DiusulkanHapus => [self::Dihapus, self::Aktif, self::Hilang],
            self::Dihapus => [],
        };
    }

    public function dapatBerpindahKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), true);
    }

    /** @return array<string, string> */
    public static function opsi(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
