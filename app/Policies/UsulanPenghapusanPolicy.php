<?php

namespace App\Policies;

use App\Enums\StatusUsulanHapus;
use App\Models\User;
use App\Models\UsulanPenghapusan;

/** Buat/kelola: penghapusan.kelola (admin-bmn). Putuskan (setujui internal): penghapusan.putuskan (pejabat-penatausahaan). */
class UsulanPenghapusanPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('penghapusan.kelola') || $pelaku->can('penghapusan.putuskan');
    }

    public function view(User $pelaku, UsulanPenghapusan $usulan): bool
    {
        if ($pelaku->can('penghapusan.kelola')) {
            return true;
        }

        // pejabat hanya melihat usulan yang sudah diajukan ke mereka
        return $pelaku->can('penghapusan.putuskan') && $usulan->status !== StatusUsulanHapus::Draf;
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('penghapusan.kelola');
    }

    public function kelola(User $pelaku, UsulanPenghapusan $usulan): bool
    {
        return $pelaku->can('penghapusan.kelola');
    }

    public function putuskan(User $pelaku, UsulanPenghapusan $usulan): bool
    {
        return $pelaku->can('penghapusan.putuskan');
    }

    public function update(User $pelaku, UsulanPenghapusan $usulan): bool
    {
        return false;
    }

    public function delete(User $pelaku, UsulanPenghapusan $usulan): bool
    {
        return false;
    }
}
