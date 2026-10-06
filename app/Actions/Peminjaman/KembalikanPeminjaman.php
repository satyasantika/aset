<?php

namespace App\Actions\Peminjaman;

use App\Actions\Aset\UbahKondisiAset;
use App\Actions\Pemeliharaan\BukaTiketPemeliharaan;
use App\Enums\KondisiAset;
use App\Enums\StatusPeminjaman;
use App\Models\Peminjaman;
use App\Models\PeminjamanItem;
use App\Models\User;
use App\Support\LockAset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-09/BR-10: pengembalian dilakukan SEKALIGUS untuk seluruh item dengan `kondisi_saat_kembali` wajib per item.
 * Pengembalian sebagian ditolak (tidak ada status "sebagian"): barang yang belum kembali berarti peminjaman belum
 * dapat ditutup. Kondisi berbeda dari saat pinjam → kondisi aset diperbarui (sumber `peminjaman`, BR-03); RR/RB →
 * tiket pemeliharaan dibuka (stub F8).
 */
class KembalikanPeminjaman
{
    /**
     * @param  array<string, string|KondisiAset>  $kondisiPerAset  Kunci: aset_id → kondisi (B/RR/RB) saat kembali.
     * @param  array<string, string|null>  $catatanPerAset  Opsional: aset_id → catatan.
     */
    public function handle(Peminjaman $peminjaman, array $kondisiPerAset, User $pelaku, array $catatanPerAset = []): Peminjaman
    {
        Gate::forUser($pelaku)->authorize('putuskan', $peminjaman);

        $ids = $peminjaman->item()->pluck('aset_id')->all();

        return LockAset::dengan('pinjam', $ids, fn (): Peminjaman => DB::transaction(
            fn (): Peminjaman => $this->kembalikan($peminjaman, $kondisiPerAset, $pelaku, $catatanPerAset),
        ));
    }

    /**
     * @param  array<string, string|KondisiAset>  $kondisiPerAset
     * @param  array<string, string|null>  $catatanPerAset
     */
    private function kembalikan(Peminjaman $peminjaman, array $kondisiPerAset, User $pelaku, array $catatanPerAset): Peminjaman
    {
        /** @var Peminjaman $terkunci */
        $terkunci = Peminjaman::query()->lockForUpdate()->findOrFail($peminjaman->getKey());

        if ($terkunci->status !== StatusPeminjaman::Dipinjam) {
            throw Konsep::sudahDiputuskan($terkunci, 'dikembalikan');
        }

        $item = $terkunci->item()->with('aset')->get();
        $kondisi = $this->validasiKondisi($item->pluck('aset_id')->all(), $kondisiPerAset);

        $ubah = app(UbahKondisiAset::class);
        $tiket = app(BukaTiketPemeliharaan::class);

        /** @var PeminjamanItem $baris */
        foreach ($item as $baris) {
            $kembali = $kondisi[$baris->aset_id];

            $baris->update(['kondisi_saat_kembali' => $kembali, 'catatan' => $catatanPerAset[$baris->aset_id] ?? $baris->catatan]);

            if ($kembali !== $baris->kondisi_saat_pinjam) {
                $ubah->handle($baris->aset, $kembali, $pelaku, 'peminjaman', $terkunci->getKey(), "Kondisi saat pengembalian {$terkunci->nomor}");

                if ($kembali !== KondisiAset::Baik) {
                    $tiket->handle($baris->aset, 'peminjaman', $terkunci->getKey(), "Dikembalikan dalam kondisi {$kembali->label()} (peminjaman {$terkunci->nomor})", $pelaku);
                }
            }
        }

        $terkunci->update([
            'status' => StatusPeminjaman::Dikembalikan,
            'dikembalikan_pada' => now(),
            'diterima_kembali_oleh' => $pelaku->getKey(),
        ]);

        $peminjaman->setRawAttributes($terkunci->getAttributes(), true);

        return $terkunci;
    }

    /**
     * @param  list<string>  $idItem
     * @param  array<string, string|KondisiAset>  $kondisiPerAset
     * @return array<string, KondisiAset>
     */
    private function validasiKondisi(array $idItem, array $kondisiPerAset): array
    {
        $galat = [];
        $hasil = [];

        foreach ($idItem as $id) {
            $nilai = $kondisiPerAset[$id] ?? null;
            $kondisi = $nilai instanceof KondisiAset ? $nilai : (is_string($nilai) ? KondisiAset::tryFrom($nilai) : null);

            if ($kondisi === null) {
                $galat[] = "Kondisi saat kembali wajib diisi untuk setiap barang (barang {$id}).";
            } else {
                $hasil[$id] = $kondisi;
            }
        }

        $asing = array_diff(array_keys($kondisiPerAset), $idItem);

        if ($asing !== []) {
            $galat[] = 'Ada barang yang bukan bagian dari peminjaman ini.';
        }

        if ($galat !== []) {
            throw ValidationException::withMessages(['kondisi' => $galat]);
        }

        return $hasil;
    }
}
