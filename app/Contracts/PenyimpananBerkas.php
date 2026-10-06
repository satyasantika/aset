<?php

namespace App\Contracts;

use App\Models\TautanBerkas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Satu-satunya pintu akses berkas (STANDAR-TEKNIS §1a.7). Implementasi aktif: TautanEksternal.
 * Kelak dapat ditambah DiskLokal/S3 tanpa mengubah modul.
 */
interface PenyimpananBerkas
{
    public function tambah(Model $pemilik, string $jenis, string $label, string $url, ?User $oleh = null): TautanBerkas;

    /** @return Collection<int, TautanBerkas> */
    public function daftar(Model $pemilik, ?string $jenis = null): Collection;

    public function perbarui(TautanBerkas $tautan, string $url, ?string $label = null): TautanBerkas;

    public function hapus(TautanBerkas $tautan): void;
}
