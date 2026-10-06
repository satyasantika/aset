<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Policy master data: seluruh aksi memerlukan izin `master.kelola` (super-admin lewat Gate::before). */
class MasterPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('master.kelola');
    }

    public function view(User $pelaku, Model $model): bool
    {
        return $this->viewAny($pelaku);
    }

    public function create(User $pelaku): bool
    {
        return $this->viewAny($pelaku);
    }

    public function update(User $pelaku, Model $model): bool
    {
        return $this->viewAny($pelaku);
    }

    public function delete(User $pelaku, Model $model): bool
    {
        return $this->viewAny($pelaku);
    }

    public function restore(User $pelaku, Model $model): bool
    {
        return $this->viewAny($pelaku);
    }

    public function forceDelete(User $pelaku, Model $model): bool
    {
        return false;
    }
}
