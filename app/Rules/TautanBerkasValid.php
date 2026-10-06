<?php

namespace App\Rules;

use App\Support\TautanBerkasParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** STANDAR-TEKNIS §1a.2: https, domain daftar putih, bukan pemendek URL, dan bukan tautan folder (bila satu berkas). */
class TautanBerkasValid implements ValidationRule
{
    public function __construct(private readonly bool $berkasSaja = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > (int) config('berkas.panjang_url_maks')) {
            $fail('Tautan berkas tidak valid.');

            return;
        }

        if (! str_starts_with(strtolower($value), 'https://')) {
            $fail('Tautan harus memakai https://.');

            return;
        }

        if (TautanBerkasParser::pemendekUrl($value)) {
            $fail('Pemendek URL tidak diperbolehkan; gunakan tautan asli berkas.');

            return;
        }

        if (! TautanBerkasParser::domainDiizinkan($value)) {
            $fail('Domain tautan tidak diizinkan. Gunakan Google Drive/Docs akun unsil.ac.id atau domain unsil.ac.id.');

            return;
        }

        if ($this->berkasSaja && TautanBerkasParser::tautanFolder($value)) {
            $fail('Tautan folder tidak diperbolehkan; berikan tautan ke satu berkas.');
        }
    }
}
