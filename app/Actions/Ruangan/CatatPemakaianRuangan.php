<?php

namespace App\Actions\Ruangan;

use App\Exceptions\PemakaianBentrok;
use App\Models\PemakaianRuangan;
use App\Models\Ruangan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mencatat pemakaian ruangan (integrasi Surat). Cek bentrok dalam transaksi dengan `lockForUpdate` pada baris ruangan
 * sehingga dua klien serentak tidak dapat memesan rentang yang sama. Idempoten lewat `referensi_eksternal` (per sumber):
 * pengiriman ulang untuk pemakaian yang sama mengembalikan catatan semula (`dibuat = false`).
 */
class CatatPemakaianRuangan
{
    /** @return array{pemakaian: PemakaianRuangan, dibuat: bool} */
    public function handle(Ruangan $ruangan, CarbonInterface $mulai, CarbonInterface $selesai, string $kegiatan, ?string $referensi, string $sumber, ?User $pelaku): array
    {
        if (! $ruangan->dapat_dipinjam) {
            throw ValidationException::withMessages(['ruangan' => "Ruangan {$ruangan->kode} tidak dapat dipinjam/dipakai."]);
        }

        if ($selesai->lte($mulai)) {
            throw ValidationException::withMessages(['selesai' => 'Waktu selesai harus setelah waktu mulai.']);
        }

        if (trim($kegiatan) === '') {
            throw ValidationException::withMessages(['kegiatan' => 'Kegiatan wajib diisi.']);
        }

        return DB::transaction(function () use ($ruangan, $mulai, $selesai, $kegiatan, $referensi, $sumber, $pelaku): array {
            Ruangan::query()->whereKey($ruangan->getKey())->lockForUpdate()->firstOrFail();

            if ($referensi !== null) {
                $ada = PemakaianRuangan::query()->where('sumber', $sumber)->where('referensi_eksternal', $referensi)->first();

                if ($ada !== null) {
                    $sama = $ada->ruangan_id === $ruangan->getKey() && $ada->mulai->equalTo($mulai) && $ada->selesai->equalTo($selesai);

                    if (! $sama) {
                        throw new PemakaianBentrok("Referensi {$referensi} sudah dipakai untuk pemakaian lain.", [$ada]);
                    }

                    return ['pemakaian' => $ada, 'dibuat' => false];
                }
            }

            $bentrok = PemakaianRuangan::query()->where('ruangan_id', $ruangan->getKey())->beririsan($mulai, $selesai)->orderBy('mulai')->get();

            if ($bentrok->isNotEmpty()) {
                throw new PemakaianBentrok("Ruangan {$ruangan->kode} sudah terpakai pada rentang tersebut.", $bentrok->all());
            }

            $pemakaian = PemakaianRuangan::query()->create([
                'ruangan_id' => $ruangan->getKey(),
                'mulai' => $mulai,
                'selesai' => $selesai,
                'kegiatan' => trim($kegiatan),
                'sumber' => $sumber,
                'referensi_eksternal' => $referensi,
                'status' => PemakaianRuangan::STATUS_TERJADWAL,
                'dicatat_oleh' => $pelaku?->getKey(),
            ]);

            return ['pemakaian' => $pemakaian, 'dibuat' => true];
        });
    }
}
