<?php

namespace App\Actions\Peminjaman;

use App\Models\Peminjaman;
use App\Support\Pengaturan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Aturan bersama Action peminjaman (validasi rentang & keranjang). */
final class Konsep
{
    public const MAKS_ITEM = 100;

    /** @param  Collection<int, string>  $ids */
    public static function validasiPengajuan(Collection $ids, string $keperluan, CarbonInterface $mulai, CarbonInterface $selesai, bool $mulaiBolehLampau = false): void
    {
        $galat = [];

        if ($ids->isEmpty()) {
            $galat['aset'] = 'Pilih minimal satu aset.';
        } elseif ($ids->count() > self::MAKS_ITEM) {
            $galat['aset'] = 'Maksimal '.self::MAKS_ITEM.' aset per peminjaman.';
        }

        if (trim($keperluan) === '') {
            $galat['keperluan'] = 'Keperluan wajib diisi.';
        }

        if (! $mulaiBolehLampau && $mulai->lessThan(now()->subMinutes(5))) {
            $galat['mulai'] = 'Waktu mulai tidak boleh di masa lalu.';
        }

        $maks = (int) Pengaturan::ambil('maks_hari_pinjam', 14);

        if ($selesai->lessThanOrEqualTo($mulai)) {
            $galat['rencana_kembali'] = 'Rencana kembali harus setelah waktu mulai.';
        } elseif ($selesai->greaterThan($mulai->copy()->addDays($maks))) {
            $galat['rencana_kembali'] = "Rencana kembali melebihi batas maksimal {$maks} hari.";
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
    }

    public static function sudahDiputuskan(Peminjaman $peminjaman, string $aksi): ValidationException
    {
        return ValidationException::withMessages([
            'status' => "Peminjaman {$peminjaman->nomor} berstatus {$peminjaman->status->label()}; tidak dapat {$aksi}.",
        ]);
    }
}
