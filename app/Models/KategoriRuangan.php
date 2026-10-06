<?php

namespace App\Models;

use App\Concerns\MembersihkanCacheMaster;
use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriRuangan extends Model
{
    use HasUuids, MembersihkanCacheMaster, SoftDeletes, TercatatAktivitas;

    protected $table = 'kategori_ruangan';

    protected $fillable = ['nama', 'adalah_laboratorium', 'adalah_ruang_kelas'];

    protected function casts(): array
    {
        return ['adalah_laboratorium' => 'boolean', 'adalah_ruang_kelas' => 'boolean'];
    }
}
