<?php

namespace App\Models;

use App\Concerns\MembersihkanCacheMaster;
use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gedung extends Model
{
    use HasUuids, MembersihkanCacheMaster, SoftDeletes, TercatatAktivitas;

    protected $table = 'gedung';

    protected $fillable = ['kode', 'nama', 'alamat'];
}
