<?php

namespace App\Policies;

use App\Enums\StatusPeriodeInventarisasi;
use App\Models\InventarisasiRuangan;
use App\Models\PeriodeInventarisasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Kelola periode (buat, buka, tutup, tugaskan): inventarisasi.kelola (admin). Pindai: inventarisasi.pindai untuk admin,
 * PIC ruangan itu, atau petugas yang ditugaskan (BR-05). Sahkan berita acara: inventarisasi.sahkan (pejabat).
 */
class PeriodeInventarisasiPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('inventarisasi.kelola') || $pelaku->can('inventarisasi.pindai') || $pelaku->can('inventarisasi.sahkan');
    }

    public function view(User $pelaku, PeriodeInventarisasi $periode): bool
    {
        if ($pelaku->hasAnyRole(['super-admin', 'admin-bmn', 'pejabat-penatausahaan'])) {
            return true;
        }

        return $this->viewAny($pelaku) && DB::table('inventarisasi_ruangan')
            ->where('periode_id', $periode->getKey())
            ->where(fn ($q) => $q
                ->whereIn('ruangan_id', DB::table('ruangan_pic')->where('user_id', $pelaku->getKey())->select('ruangan_id'))
                ->orWhereIn('id', DB::table('inventarisasi_petugas')->where('user_id', $pelaku->getKey())->select('inventarisasi_ruangan_id')))
            ->exists();
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('inventarisasi.kelola');
    }

    public function buka(User $pelaku, PeriodeInventarisasi $periode): bool
    {
        return $pelaku->can('inventarisasi.kelola');
    }

    public function tutup(User $pelaku, PeriodeInventarisasi $periode): bool
    {
        return $pelaku->can('inventarisasi.kelola');
    }

    public function sahkan(User $pelaku, PeriodeInventarisasi $periode): bool
    {
        return $pelaku->can('inventarisasi.sahkan') && $periode->status === StatusPeriodeInventarisasi::Ditutup;
    }

    public function tugaskan(User $pelaku, InventarisasiRuangan $inventarisasi): bool
    {
        return $pelaku->can('inventarisasi.kelola');
    }

    /** Memindai/mencatat hasil pada satu ruangan-periode (periode harus berjalan). */
    public function pindai(User $pelaku, InventarisasiRuangan $inventarisasi): bool
    {
        if (! $pelaku->can('inventarisasi.pindai') || $inventarisasi->periode->status !== StatusPeriodeInventarisasi::Berjalan) {
            return false;
        }

        return $pelaku->hasAnyRole(['super-admin', 'admin-bmn'])
            || $pelaku->bolehMengelolaRuangan($inventarisasi->ruangan_id)
            || $inventarisasi->petugas()->whereKey($pelaku->getKey())->exists();
    }

    public function update(User $pelaku, PeriodeInventarisasi $periode): bool
    {
        return false;
    }

    public function delete(User $pelaku, PeriodeInventarisasi $periode): bool
    {
        return false;
    }
}
