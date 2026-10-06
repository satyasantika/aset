<?php

namespace App\Concerns;

use App\Support\Dasbor;
use Illuminate\Database\Eloquent\Model;

/** Model yang memengaruhi angka dasbor: setiap perubahan menaikkan versi cache statistik. */
trait MembersihkanCacheStatistik
{
    protected static function bootMembersihkanCacheStatistik(): void
    {
        $kedaluwarsa = fn (Model $m) => Dasbor::tandaiKedaluwarsa();

        static::saved($kedaluwarsa);
        static::deleted($kedaluwarsa);
    }
}
