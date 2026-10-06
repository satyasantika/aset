<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity as Induk;

class Aktivitas extends Induk
{
    use HasUuids;
}
