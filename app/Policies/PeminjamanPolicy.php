<?php

namespace App\Policies;

use App\Enums\JenisPeminjam;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\User;

/**
 * Catat langsung: PIC untuk aset di ruangannya (BR-05) atau admin. Putuskan: PIC ruangan asal/admin untuk peminjaman
 * internal; pejabat-penatausahaan untuk pihak luar (BR-07). Data pribadi peminjam: BR-23.
 */
class PeminjamanPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('peminjaman.catat') || $pelaku->can('peminjaman.putuskan') || $pelaku->can('peminjaman.ajukan');
    }

    public function view(User $pelaku, Peminjaman $peminjaman): bool
    {
        if ($peminjaman->peminjam_user_id === $pelaku->getKey()) {
            return true;
        }

        return $this->mengurus($pelaku, $peminjaman) || $pelaku->can('peminjaman.putuskan') && $pelaku->hasRole('pejabat-penatausahaan');
    }

    /** Mencatat peminjaman langsung untuk satu aset. */
    public function catat(User $pelaku, Aset $aset): bool
    {
        return $pelaku->can('peminjaman.catat') && $pelaku->bolehMengelolaRuangan($aset->ruangan_id);
    }

    public function ajukan(User $pelaku): bool
    {
        return $pelaku->can('peminjaman.ajukan');
    }

    /** Admin mencatat permohonan pihak luar (diteruskan ke pejabat-penatausahaan, BR-07). */
    public function catatPihakLuar(User $pelaku): bool
    {
        return $pelaku->hasAnyRole(['super-admin', 'admin-bmn']);
    }

    /** Menyetujui/menolak/menyerahkan/menerima kembali. */
    public function putuskan(User $pelaku, Peminjaman $peminjaman): bool
    {
        if ($peminjaman->jenis_peminjam === JenisPeminjam::PihakLuar) {
            return $pelaku->hasAnyRole(['pejabat-penatausahaan', 'admin-bmn']) && $pelaku->can('peminjaman.putuskan');
        }

        return $this->mengurus($pelaku, $peminjaman);
    }

    public function batalkan(User $pelaku, Peminjaman $peminjaman): bool
    {
        return $peminjaman->peminjam_user_id === $pelaku->getKey() || $this->mengurus($pelaku, $peminjaman);
    }

    /** Data pribadi peminjam (nama, kontak, unit) — BR-23. */
    public function lihatDataPribadi(User $pelaku, Peminjaman $peminjaman): bool
    {
        return $peminjaman->peminjam_user_id === $pelaku->getKey()
            || ($pelaku->can('data-pribadi.lihat') && $this->mengurus($pelaku, $peminjaman))
            || ($pelaku->can('data-pribadi.lihat') && $pelaku->hasAnyRole(['pejabat-penatausahaan', 'admin-bmn', 'super-admin']));
    }

    public function update(User $pelaku, Peminjaman $peminjaman): bool
    {
        return false;
    }

    public function delete(User $pelaku, Peminjaman $peminjaman): bool
    {
        return false;
    }

    /** PIC (R) atau admin yang mengelola ruangan asal SEMUA aset dalam peminjaman ini. */
    private function mengurus(User $pelaku, Peminjaman $peminjaman): bool
    {
        if (! $pelaku->can('peminjaman.putuskan') && ! $pelaku->can('peminjaman.catat')) {
            return false;
        }

        if ($pelaku->hasAnyRole(['super-admin', 'admin-bmn'])) {
            return true;
        }

        $ruangan = $peminjaman->item()->join('aset', 'aset.id', '=', 'peminjaman_item.aset_id')->pluck('aset.ruangan_id')->unique();

        return $ruangan->isNotEmpty() && $ruangan->every(fn (?string $id) => $pelaku->bolehMengelolaRuangan($id));
    }
}
