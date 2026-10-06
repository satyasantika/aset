<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Satu baris kunci/nilai pada tabel `pengaturan`. Akses lewat App\Support\Pengaturan. */
class PengaturanItem extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'pengaturan';

    protected $fillable = ['kunci', 'nilai'];

    protected function casts(): array
    {
        return ['nilai' => 'json'];
    }
}
