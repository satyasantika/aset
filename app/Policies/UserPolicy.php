<?php

namespace App\Policies;

use App\Models\User;

/** Kelola pengguna: izin `pengguna.kelola`; hanya super-admin yang menyentuh akun super-admin/admin-bmn. */
class UserPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('pengguna.kelola');
    }

    public function view(User $pelaku, User $target): bool
    {
        return $this->viewAny($pelaku);
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('pengguna.kelola');
    }

    public function update(User $pelaku, User $target): bool
    {
        return $pelaku->can('pengguna.kelola') && ! $target->hasAnyRole(['super-admin', 'admin-bmn']);
    }

    /** Akun tidak dihapus; gunakan penonaktifan (aktif = false). */
    public function delete(User $pelaku, User $target): bool
    {
        return false;
    }
}
