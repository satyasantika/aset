<?php

namespace App\Policies;

use App\Models\Aset;
use App\Models\TiketPemeliharaan;
use App\Models\User;

/** Kelola tiket: izin `pemeliharaan.kelola`; PIC hanya untuk aset di ruangannya (BR-05). Data pelapor: BR-23. */
class TiketPemeliharaanPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('pemeliharaan.kelola');
    }

    public function view(User $pelaku, TiketPemeliharaan $tiket): bool
    {
        return $pelaku->can('pemeliharaan.kelola') && $pelaku->bolehMengelolaRuangan($tiket->aset->ruangan_id);
    }

    /** Membuka tiket manual untuk aset tertentu. */
    public function buka(User $pelaku, Aset $aset): bool
    {
        return $pelaku->can('pemeliharaan.kelola') && $pelaku->bolehMengelolaRuangan($aset->ruangan_id);
    }

    /** Memproses, menyelesaikan, atau menutup tiket. */
    public function kelola(User $pelaku, TiketPemeliharaan $tiket): bool
    {
        return $this->view($pelaku, $tiket);
    }

    public function lihatDataPelapor(User $pelaku, TiketPemeliharaan $tiket): bool
    {
        return $pelaku->can('data-pribadi.lihat') && $this->view($pelaku, $tiket);
    }

    public function create(User $pelaku): bool
    {
        return false; // dibuka lewat Action (aset, laporan, peminjaman)
    }

    public function update(User $pelaku, TiketPemeliharaan $tiket): bool
    {
        return false;
    }

    public function delete(User $pelaku, TiketPemeliharaan $tiket): bool
    {
        return false;
    }
}
