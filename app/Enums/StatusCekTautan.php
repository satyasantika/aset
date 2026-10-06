<?php

namespace App\Enums;

enum StatusCekTautan: string
{
    case Belum = 'belum';
    case DapatDiakses = 'dapat_diakses';
    case TidakDapatDiakses = 'tidak_dapat_diakses';
}
