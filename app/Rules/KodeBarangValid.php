<?php

namespace App\Rules;

use App\Models\KodefikasiBarang;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Kode barang harus ada di master kodefikasi dan berada pada tingkat sub-sub kelompok (tingkat 5). */
class KodeBarangValid implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tingkat = is_string($value)
            ? KodefikasiBarang::query()->where('kode', $value)->value('tingkat')
            : null;

        if ($tingkat === null) {
            $fail('Kode barang tidak ditemukan di kodefikasi barang BMN.');

            return;
        }

        if ((int) $tingkat !== KodefikasiBarang::TINGKAT_SUB_SUB_KELOMPOK) {
            $fail('Kode barang harus berupa sub-sub kelompok (tingkat 5).');
        }
    }
}
