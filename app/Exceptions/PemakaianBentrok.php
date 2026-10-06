<?php

namespace App\Exceptions;

use App\Models\PemakaianRuangan;
use RuntimeException;

/** Pemakaian ruangan bentrok dengan jadwal lain, atau referensi eksternal sudah dipakai untuk pemakaian berbeda (HTTP 409). */
class PemakaianBentrok extends RuntimeException
{
    /** @param  list<PemakaianRuangan>  $bentrok */
    public function __construct(string $pesan, public readonly array $bentrok = [])
    {
        parent::__construct($pesan);
    }
}
