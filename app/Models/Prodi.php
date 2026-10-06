<?php

namespace App\Models;

use App\Concerns\MembersihkanCacheMaster;
use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prodi extends Model
{
    use HasUuids, MembersihkanCacheMaster, SoftDeletes, TercatatAktivitas;

    protected $table = 'prodi';

    protected $fillable = ['kode', 'nama', 'kode_eksternal'];
}
