<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;

/**
 * QR dibangkitkan di server (endroid/qr-code). TIDAK memakai layanan QR pihak ketiga (temuan R-16):
 * data tidak pernah meninggalkan server dan cetak label tidak bergantung pada layanan luar.
 */
class GeneratorQr
{
    public function dataUri(string $isi, int $ukuran = 240): string
    {
        return (new Builder)->build(data: $isi, size: $ukuran, margin: 4)->getDataUri();
    }
}
