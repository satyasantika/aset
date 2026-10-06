<?php

namespace App\Actions\Migrasi\Siman2;

use App\Models\ImporSiman2Log;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Keadaan bersama satu kali impor: mode (dry-run, NUP berurutan), pemetaan username → surel, laporan, dan
 * pencatatan `impor_siman2_log` (prinsip: setiap baris sumber tercatat; idempoten lewat id lama).
 */
class Konteks
{
    public LaporanImpor $laporan;

    /**
     * @param  array<string, string>  $pemetaanPengguna  username (huruf kecil) → surel
     */
    public function __construct(
        public readonly bool $dryRun = false,
        public readonly bool $nupBerurutan = false,
        public readonly array $pemetaanPengguna = [],
    ) {
        $this->laporan = new LaporanImpor;
    }

    /** id baru hasil impor sebelumnya untuk (sheet, id lama), atau null. */
    public function idBaru(string $sheet, string|int $idLama): ?string
    {
        return ImporSiman2Log::query()
            ->where('sheet', $sheet)->where('id_lama', (string) $idLama)->where('status', ImporSiman2Log::OK)
            ->value('id_baru');
    }

    public function catat(string $sheet, string|int $idLama, ?string $idBaru, string $status, ?string $pesan = null): void
    {
        ImporSiman2Log::query()->updateOrCreate(
            ['sheet' => $sheet, 'id_lama' => (string) $idLama],
            ['id_baru' => $idBaru, 'status' => $status, 'pesan' => $pesan],
        );
        $this->laporan->hitung($sheet, $status);
    }

    /**
     * Memproses satu baris sumber secara terisolasi (savepoint): galat pada satu baris tidak menggagalkan baris lain.
     *
     * @param  Closure(): ?string  $olah  Mengembalikan id baru (atau null bila dilewati).
     */
    public function proses(string $sheet, string|int $idLama, Closure $olah): void
    {
        try {
            $idBaru = DB::transaction($olah);
            $this->catat($sheet, $idLama, $idBaru, $idBaru === null ? ImporSiman2Log::DILEWATI : ImporSiman2Log::OK);
        } catch (Throwable $e) {
            $pesan = $e instanceof ValidationException
                ? collect($e->errors())->flatten()->implode(' ')
                : $e->getMessage();

            $this->laporan->tambahGalat($sheet, (string) $idLama, $pesan);
            ImporSiman2Log::query()->updateOrCreate(
                ['sheet' => $sheet, 'id_lama' => (string) $idLama],
                ['id_baru' => null, 'status' => ImporSiman2Log::GALAT, 'pesan' => mb_substr($pesan, 0, 1000)],
            );
        }
    }

    public function peringatan(string $sheet, string|int $idLama, string $pesan): void
    {
        $this->laporan->tambahPeringatan($sheet, (string) $idLama, $pesan);
    }
}
