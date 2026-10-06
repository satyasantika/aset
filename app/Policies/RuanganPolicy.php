<?php

namespace App\Policies;

use App\Models\Ruangan;
use App\Models\User;

/** Lihat: semua peran staf (aset.lihat). Ubah & penugasan PIC: master.kelola. */
class RuanganPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('aset.lihat');
    }

    public function view(User $pelaku, Ruangan $ruangan): bool
    {
        return $this->viewAny($pelaku);
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function update(User $pelaku, Ruangan $ruangan): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function delete(User $pelaku, Ruangan $ruangan): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function restore(User $pelaku, Ruangan $ruangan): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function forceDelete(User $pelaku, Ruangan $ruangan): bool
    {
        return false;
    }
}
