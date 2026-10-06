<?php

namespace App\Actions\Migrasi\Siman2;

use App\Enums\KondisiAset;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

/** Pengurai nilai mentah sheet SIMAN-2 (teks tanggal Indonesia, JSON, kondisi, angka). */
final class Nilai
{
    private const BULAN = [
        'jan' => 1, 'januari' => 1, 'feb' => 2, 'februari' => 2, 'mar' => 3, 'maret' => 3, 'apr' => 4, 'april' => 4,
        'mei' => 5, 'jun' => 6, 'juni' => 6, 'jul' => 7, 'juli' => 7, 'agu' => 8, 'agt' => 8, 'agus' => 8, 'agustus' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9, 'okt' => 10, 'oktober' => 10, 'nov' => 11, 'november' => 11,
        'des' => 12, 'desember' => 12,
    ];

    public static function teks(mixed $nilai): string
    {
        if ($nilai instanceof DateTimeInterface) {
            return $nilai->format('Y-m-d H:i:s');
        }

        if (is_float($nilai) && floor($nilai) === $nilai) {
            return (string) (int) $nilai;
        }

        return trim((string) ($nilai ?? ''));
    }

    public static function tidakKosong(mixed $nilai): ?string
    {
        $t = self::teks($nilai);

        return $t === '' ? null : $t;
    }

    public static function angka(mixed $nilai): ?int
    {
        $t = preg_replace('/[^0-9]/', '', self::teks($nilai));

        return $t === '' || $t === null ? null : (int) $t;
    }

    /** "Baik" → B, "Rusak Ringan" → RR, "Rusak Berat" → RB; selain itu null (mis. "Hilang"). */
    public static function kondisi(mixed $nilai): ?KondisiAset
    {
        return match (mb_strtolower(self::teks($nilai))) {
            'baik', 'b' => KondisiAset::Baik,
            'rusak ringan', 'rr' => KondisiAset::RusakRingan,
            'rusak berat', 'rb' => KondisiAset::RusakBerat,
            default => null,
        };
    }

    /**
     * Daftar dari sel yang berisi JSON (`[1,2]`), pemisah koma, atau kosong.
     *
     * @return list<string>
     */
    public static function daftar(mixed $nilai): array
    {
        $t = self::teks($nilai);

        if ($t === '') {
            return [];
        }

        $json = json_decode($t, true);

        if (is_array($json)) {
            return array_values(array_map(fn ($v) => trim((string) $v), array_filter($json, fn ($v) => $v !== null && $v !== '')));
        }

        return array_values(array_filter(array_map('trim', explode(',', $t)), fn ($v) => $v !== ''));
    }

    /**
     * Peta dari sel JSON objek `{ "2": "Rusak Ringan" }`.
     *
     * @return array<int|string, string>
     */
    public static function peta(mixed $nilai): array
    {
        $json = json_decode(self::teks($nilai), true);

        return is_array($json) ? array_map(fn ($v) => (string) $v, $json) : [];
    }

    /**
     * Menerima: `dd/mm/yyyy HH:ii`, `d Mon yyyy, HH.ii`, `yyyy-mm-dd[ HH:ii]`, objek tanggal Excel.
     * Zona waktu Asia/Jakarta. Mengembalikan null bila tidak dikenali.
     */
    public static function tanggal(mixed $nilai): ?CarbonInterface
    {
        if ($nilai instanceof DateTimeInterface) {
            return Carbon::instance($nilai)->setTimezone(config('app.timezone'));
        }

        $t = self::teks($nilai);

        if ($t === '') {
            return null;
        }

        $zona = config('app.timezone');

        if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})(?:[ ,]+(\d{1,2})[:.](\d{2}))?~', $t, $m)) {
            return Carbon::create((int) $m[3], (int) $m[2], (int) $m[1], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), 0, $zona) ?: null;
        }

        if (preg_match('~^(\d{4})-(\d{2})-(\d{2})(?:[ T]+(\d{1,2})[:.](\d{2}))?~', $t, $m)) {
            return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), 0, $zona) ?: null;
        }

        if (preg_match('~^(\d{1,2})\s+([A-Za-z]+)\.?\s+(\d{4})(?:[ ,]+(\d{1,2})[:.](\d{2}))?~', $t, $m)) {
            $bulan = self::BULAN[mb_strtolower($m[2])] ?? null;

            return $bulan ? (Carbon::create((int) $m[3], $bulan, (int) $m[1], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), 0, $zona) ?: null) : null;
        }

        return null;
    }

    /** Nama bulan Indonesia ("November") → angka 1–12, atau null. */
    public static function bulan(mixed $nilai): ?int
    {
        return self::BULAN[mb_strtolower(self::teks($nilai))] ?? null;
    }
}
