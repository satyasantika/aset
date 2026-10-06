<?php

namespace App\Policies;

use App\Models\Aset;
use App\Models\User;

/**
 * Lihat: semua staf (aset.lihat). Ubah data induk: aset.kelola. Tindakan operasional (kondisi, label, ...) bagi
 * PIC dibatasi ke ruangan yang ditugaskan (BR-05); admin: semua ruangan.
 */
class AsetPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('aset.lihat');
    }

    public function view(User $pelaku, Aset $aset): bool
    {
        return $pelaku->can('aset.lihat');
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('aset.kelola');
    }

    public function update(User $pelaku, Aset $aset): bool
    {
        return $pelaku->can('aset.kelola');
    }

    public function ubahKondisi(User $pelaku, Aset $aset): bool
    {
        return $pelaku->can('aset.ubah-kondisi') && $pelaku->bolehMengelolaRuangan($aset->ruangan_id);
    }

    public function ubahStatus(User $pelaku, Aset $aset): bool
    {
        return $pelaku->can('aset.kelola');
    }

    public function cetakLabel(User $pelaku, Aset $aset): bool
    {
        return $pelaku->can('label.cetak') && $pelaku->bolehMengelolaRuangan($aset->ruangan_id);
    }

    /** Aset tidak pernah dihapus permanen; hapus lunak hanya untuk salah input (super-admin lewat Gate::before). */
    public function delete(User $pelaku, Aset $aset): bool
    {
        return false;
    }

    public function restore(User $pelaku, Aset $aset): bool
    {
        return false;
    }

    public function forceDelete(User $pelaku, Aset $aset): bool
    {
        return false;
    }
}
