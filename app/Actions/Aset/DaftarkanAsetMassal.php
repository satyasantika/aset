<?php

namespace App\Actions\Aset;

use App\Enums\StatusBmn;
use App\Models\Aset;
use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * US-AST-02: n unit sekaligus → n baris `aset` (NUP awal + i), tiap unit tetap baris sendiri, satu transaksi
 * (semua atau tidak sama sekali) dan satu `kelompok_pengadaan`.
 */
class DaftarkanAsetMassal
{
    public const MAKS_UNIT = 500;

    /**
     * @param  array<string, mixed>  $data  Data induk yang sama untuk semua unit.
     * @return Collection<int, Aset>
     */
    public function handle(array $data, int $jumlah, ?int $nupAwal, User $pelaku, ?string $prefixKodeInternal = null): Collection
    {
        Gate::forUser($pelaku)->authorize('create', Aset::class);
        Pengaturan::pastikanFitur('tambah_aset');

        if ($jumlah < 1 || $jumlah > self::MAKS_UNIT) {
            throw ValidationException::withMessages(['jumlah' => 'Jumlah unit harus 1–'.self::MAKS_UNIT.'.']);
        }

        $tercatat = ($data['status_bmn'] ?? StatusBmn::Tercatat->value) === StatusBmn::Tercatat->value
            || ($data['status_bmn'] ?? null) === StatusBmn::Tercatat;

        $data['kelompok_pengadaan'] ??= 'PGD-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        unset($data['nup'], $data['kode_internal']);

        return DB::transaction(function () use ($data, $jumlah, $nupAwal, $pelaku, $prefixKodeInternal, $tercatat): Collection {
            if ($tercatat) {
                $nupAwal ??= ((int) Aset::withTrashed()->where('kode_barang', $data['kode_barang'] ?? null)->max('nup')) + 1;
                $this->pastikanNupBebas((string) ($data['kode_barang'] ?? ''), $nupAwal, $jumlah);
            } elseif (blank($prefixKodeInternal)) {
                throw ValidationException::withMessages(['kode_internal' => 'Awalan kode internal wajib untuk barang yang belum tercatat.']);
            }

            $daftar = app(DaftarkanAset::class);
            $hasil = collect();

            for ($i = 0; $i < $jumlah; $i++) {
                $unit = $tercatat
                    ? [...$data, 'nup' => $nupAwal + $i]
                    : [...$data, 'kode_internal' => $prefixKodeInternal.'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)];

                $aset = Aset::query()->create($daftar->bersihkan($unit));
                DaftarkanAset::tulisRiwayatAwal($aset, $pelaku);
                $hasil->push($aset);
            }

            return $hasil;
        });
    }

    private function pastikanNupBebas(string $kodeBarang, int $nupAwal, int $jumlah): void
    {
        $terpakai = Aset::withTrashed()
            ->where('kode_barang', $kodeBarang)
            ->whereBetween('nup', [$nupAwal, $nupAwal + $jumlah - 1])
            ->pluck('nup');

        if ($terpakai->isNotEmpty()) {
            throw ValidationException::withMessages([
                'nup_awal' => 'NUP sudah dipakai untuk kode barang ini: '.$terpakai->sort()->implode(', ').'.',
            ]);
        }
    }
}
