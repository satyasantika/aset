<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken as Induk;

class TokenAkses extends Induk
{
    use HasUuids;

    protected $table = 'personal_access_tokens';
}
