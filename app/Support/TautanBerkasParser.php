<?php

namespace App\Support;

use App\Enums\PenyediaBerkas;

/** Menganalisis URL berkas: penyedia, domain yang diizinkan, pemendek URL, tautan folder, dan id berkas Drive. */
class TautanBerkasParser
{
    public static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower(rtrim($host, '.')) : null;
    }

    public static function hostCocok(string $host, string $pola): bool
    {
        $pola = strtolower($pola);

        if (str_starts_with($pola, '*.')) {
            return str_ends_with($host, substr($pola, 1)) && $host !== substr($pola, 2);
        }

        return $host === $pola;
    }

    public static function domainDiizinkan(string $url): bool
    {
        $host = self::host($url);

        if ($host === null || ! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }

        foreach ((array) config('berkas.domain_diizinkan') as $pola) {
            if (self::hostCocok($host, $pola)) {
                return true;
            }
        }

        return false;
    }

    public static function pemendekUrl(string $url): bool
    {
        $host = self::host($url);

        foreach ((array) config('berkas.pemendek_url') as $pemendek) {
            if ($host !== null && ($host === $pemendek || str_ends_with($host, '.'.$pemendek))) {
                return true;
            }
        }

        return false;
    }

    public static function tautanFolder(string $url): bool
    {
        return (bool) preg_match('~/drive/(u/\d+/)?folders/|/folderview~i', $url);
    }

    public static function penyedia(string $url): PenyediaBerkas
    {
        $host = self::host($url) ?? '';

        return match (true) {
            $host === 'drive.google.com' => PenyediaBerkas::GoogleDrive,
            $host === 'docs.google.com' => PenyediaBerkas::GoogleDocs,
            $host === 'onedrive.live.com', str_ends_with($host, '.sharepoint.com') => PenyediaBerkas::Onedrive,
            $host === 'unsil.ac.id', str_ends_with($host, '.unsil.ac.id') => PenyediaBerkas::Unsil,
            default => PenyediaBerkas::Lainnya,
        };
    }

    public static function idDrive(string $url): ?string
    {
        $host = self::host($url);

        if (! in_array($host, ['drive.google.com', 'docs.google.com'], true)) {
            return null;
        }

        if (preg_match('~/(?:file|document|spreadsheets|presentation|forms)/(?:u/\d+/)?d/([A-Za-z0-9_-]{10,})~', $url, $cocok)) {
            return $cocok[1];
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $id = $query['id'] ?? null;

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{10,}$/', $id) ? $id : null;
    }

    public static function urlPratinjau(string $url): ?string
    {
        $id = self::idDrive($url);

        return $id ? "https://drive.google.com/file/d/{$id}/preview" : null;
    }

    public static function urlFoto(string $url): ?string
    {
        $id = self::idDrive($url);

        return $id ? "https://lh3.googleusercontent.com/d/{$id}" : null;
    }
}
