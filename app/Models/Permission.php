<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as Induk;

class Permission extends Induk
{
    use HasUuids;
}
