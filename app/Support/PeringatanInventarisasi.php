<?php

namespace App\Support;

use App\Enums\StatusPeriodeInventarisasi;
use App\Models\PeriodeInventarisasi;
use Illuminate\Support\Carbon;

/**
 * BR-15 / RG-04: peringatan bila inventarisasi terakhir yang DISAHKAN sudah lebih tua dari ambang (bawaan 4 tahun;
 * batas aturan 5 tahun, PMK 181/2016 Pasal 19) atau belum pernah ada.
 */
class PeringatanInventarisasi
{
    public const BATAS_ATURAN_TAHUN = 5;

    /** @return array{tingkat: string, pesan: string, terakhir: ?string}|null */
    public static function cek(): ?array
    {
        $ambang = max(1, (int) Pengaturan::ambil('ambang_pengingat_inventarisasi_tahun', 4));
        $terakhir = PeriodeInventarisasi::query()->where('status', StatusPeriodeInventarisasi::Disahkan->value)->max('disahkan_pada');

        if ($terakhir === null) {
            return ['tingkat' => 'danger', 'terakhir' => null, 'pesan' => 'Belum ada inventarisasi yang disahkan. Aturan mewajibkan inventarisasi minimal sekali dalam '.self::BATAS_ATURAN_TAHUN.' tahun.'];
        }

        $waktu = Carbon::parse($terakhir);
        $tahun = $waktu->diffInYears(now(), true);

        if ($tahun < $ambang) {
            return null;
        }

        $lewatBatas = $tahun >= self::BATAS_ATURAN_TAHUN;

        return [
            'tingkat' => $lewatBatas ? 'danger' : 'warning',
            'terakhir' => $waktu->toDateString(),
            'pesan' => 'Inventarisasi terakhir yang disahkan: '.$waktu->translatedFormat('d F Y').' ('.(int) $tahun.' tahun lalu). '
                .($lewatBatas ? 'Batas aturan '.self::BATAS_ATURAN_TAHUN.' tahun terlampaui.' : 'Segera jadwalkan inventarisasi (batas aturan '.self::BATAS_ATURAN_TAHUN.' tahun).'),
        ];
    }
}
