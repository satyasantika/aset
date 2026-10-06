<?php

namespace App\Actions\Migrasi\Siman2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sheet `laporan` → `activity_log` (log_name `siman2`). Pelaku lama disimpan sebagai properti `pelaku_lama`, BUKAN `causer`,
 * karena tidak terverifikasi (BR-21).
 */
class ImporLaporan implements Importer
{
    public const LOG = 'siman2';

    public function nama(): string
    {
        return 'laporan';
    }

    public function jalankan(Konteks $konteks, BacaXlsx $xlsx): void
    {
        foreach ($xlsx->baris('laporan') as $baris) {
            $idLama = Nilai::teks($baris['id'] ?? '');

            $konteks->proses('laporan', $idLama, function () use ($konteks, $baris, $idLama): string {
                $idBaru = $konteks->idBaru('laporan', $idLama) ?? (string) Str::uuid7();
                $waktu = Nilai::tanggal($baris['waktu'] ?? null);

                $nilai = [
                    'log_name' => self::LOG,
                    'description' => Nilai::tidakKosong($baris['aksi'] ?? null) ?? 'aksi',
                    'subject_type' => null, 'subject_id' => null, 'causer_type' => null, 'causer_id' => null, 'event' => null, 'batch_uuid' => null,
                    'properties' => json_encode([
                        'pelaku_lama' => Nilai::tidakKosong($baris['user'] ?? null),
                        'detail' => Nilai::tidakKosong($baris['detail'] ?? null),
                        'waktu_lama' => Nilai::tidakKosong($baris['waktu'] ?? null),
                        'id_lama' => $idLama,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $waktu ?? now(),
                    'updated_at' => $waktu ?? now(),
                ];

                $ada = DB::table('activity_log')->where('id', $idBaru)->exists();
                $ada ? DB::table('activity_log')->where('id', $idBaru)->update($nilai) : DB::table('activity_log')->insert(['id' => $idBaru] + $nilai);

                return $idBaru;
            });
        }
    }
}
