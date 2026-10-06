<?php

namespace App\Policies;

use App\Models\Aktivitas;
use App\Models\User;

/** Log aktivitas bersifat baca-saja untuk super-admin dan admin-bmn. */
class AktivitasPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin-bmn']);
    }

    public function view(User $user, Aktivitas $aktivitas): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Aktivitas $aktivitas): bool
    {
        return false;
    }

    public function delete(User $user, Aktivitas $aktivitas): bool
    {
        return false;
    }
}
