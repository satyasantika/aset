<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Jejak migrasi per baris sumber SIMAN-2 (sheet, id lama → id baru, status, pesan). Kunci idempotensi impor. */
class ImporSiman2Log extends Model
{
    use HasUuids;

    public const OK = 'ok';

    public const GALAT = 'galat';

    public const DILEWATI = 'dilewati';

    protected $table = 'impor_siman2_log';

    protected $fillable = ['sheet', 'id_lama', 'id_baru', 'status', 'pesan'];
}
