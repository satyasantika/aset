<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as Induk;

class Role extends Induk
{
    use HasUuids;
}
