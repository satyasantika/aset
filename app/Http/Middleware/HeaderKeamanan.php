<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan pada semua respons web & API (F12.1): CSP, X-Frame-Options, nosniff, referrer, izin kamera hanya untuk
 * situs sendiri (pemindai QR), dan HSTS di produksi. CSP mengizinkan inline/eval karena Livewire/Alpine/Filament
 * membutuhkannya; sumber lain dibatasi ke asal sendiri. Gambar boleh dari https mana pun (foto berupa tautan Drive).
 */
class HeaderKeamanan
{
    public const CSP = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' data: https://fonts.bunny.net; "
        ."img-src 'self' data: blob: https:; connect-src 'self'; media-src 'self' blob:; "
        ."object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $respons */
        $respons = $next($request);

        $respons->headers->set('Content-Security-Policy', self::CSP);
        $respons->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $respons->headers->set('X-Content-Type-Options', 'nosniff');
        $respons->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respons->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if (app()->isProduction()) {
            $respons->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respons;
    }
}
