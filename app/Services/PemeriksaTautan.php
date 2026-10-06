<?php

namespace App\Services;

use App\Enums\StatusCekTautan;
use App\Support\TautanBerkasParser;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Memeriksa keteraksesan tautan tanpa mengunduh isi (HEAD, lalu GET ber-stream). Anti-SSRF: hanya host daftar
 * putih, hanya https, IP hasil resolusi DNS harus publik, dan setiap redirect diperiksa ulang secara manual.
 * Hasil `null` = tidak dapat disimpulkan (jaringan/5xx) sehingga status lama dipertahankan.
 */
class PemeriksaTautan
{
    /** @param  (Closure(string): list<string>)|null  $resolver  Resolusi DNS (dapat diganti pada uji). */
    public function __construct(private readonly ?Closure $resolver = null) {}

    public function periksa(string $url): ?StatusCekTautan
    {
        $maks = (int) config('berkas.pemeriksaan.maks_redirect');

        for ($i = 0; $i <= $maks; $i++) {
            if (! $this->amanDikunjungi($url)) {
                return StatusCekTautan::TidakDapatDiakses;
            }

            try {
                $respons = $this->minta($url);
            } catch (ConnectionException) {
                return null;
            }

            $status = $respons->status();

            if ($status >= 200 && $status < 300) {
                return StatusCekTautan::DapatDiakses;
            }

            if ($status >= 300 && $status < 400) {
                $lokasi = $respons->header('Location');

                if ($lokasi === '') {
                    return StatusCekTautan::TidakDapatDiakses;
                }

                // Redirect ke halaman login/domain lain berarti berkas tidak terbuka bagi publik/anonim.
                $url = $this->absolut($url, $lokasi);

                continue;
            }

            if (in_array($status, [401, 403, 404, 410], true)) {
                return StatusCekTautan::TidakDapatDiakses;
            }

            return null;
        }

        return StatusCekTautan::TidakDapatDiakses;
    }

    private function minta(string $url): Response
    {
        $klien = Http::timeout((int) config('berkas.pemeriksaan.timeout_detik'))
            ->withOptions(['allow_redirects' => false, 'stream' => true])
            ->withHeaders(['User-Agent' => 'SIMAN-FKIP-PemeriksaTautan/1.0']);

        $respons = $klien->head($url);

        if (in_array($respons->status(), [405, 501], true)) {
            $respons = $klien->get($url); // tubuh tidak dibaca (stream)
        }

        return $respons;
    }

    private function amanDikunjungi(string $url): bool
    {
        if (! TautanBerkasParser::domainDiizinkan($url)) {
            return false;
        }

        $host = (string) TautanBerkasParser::host($url);
        $alamat = $this->resolver !== null ? ($this->resolver)($host) : (gethostbynamel($host) ?: []);

        if ($alamat === []) {
            return false;
        }

        foreach ($alamat as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    private function absolut(string $dasar, string $lokasi): string
    {
        if (preg_match('~^https?://~i', $lokasi)) {
            return $lokasi;
        }

        $bagian = parse_url($dasar);

        return ($bagian['scheme'] ?? 'https').'://'.($bagian['host'] ?? '').'/'.ltrim($lokasi, '/');
    }
}
