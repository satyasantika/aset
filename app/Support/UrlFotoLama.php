<?php

namespace App\Support;

/**
 * Foto SIMAN-2 memakai URL gambar Drive (`lh3.googleusercontent.com/d/<id>`, `drive.google.com/uc?id=`, ...).
 * Sistem baru hanya menyimpan tautan berkas Drive yang lolos daftar putih (STANDAR-TEKNIS §1a); id berkas dipertahankan
 * sehingga foto tetap terbuka.
 */
class UrlFotoLama
{
    /** Tautan Drive kanonik (`.../file/d/<id>/view`) atau null bila bukan foto Drive. */
    public static function keDrive(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || ! str_starts_with(strtolower($url), 'http')) {
            return null;
        }

        $host = TautanBerkasParser::host($url);
        $id = null;

        if ($host === 'lh3.googleusercontent.com' && preg_match('~/d/([A-Za-z0-9_-]{10,})~', $url, $m)) {
            $id = $m[1];
        } elseif ($host === 'drive.google.com' || $host === 'docs.google.com') {
            $id = TautanBerkasParser::idDrive($url);
        }

        return $id ? "https://drive.google.com/file/d/{$id}/view" : null;
    }
}
