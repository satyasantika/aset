<?php

namespace App\Models;

use App\Concerns\MembersihkanCacheMaster;
use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KodefikasiBarang extends Model
{
    use HasUuids, MembersihkanCacheMaster, TercatatAktivitas;

    /** Tingkat sub-sub kelompok: satu-satunya tingkat yang dapat dipakai pada aset (BR-01). */
    public const TINGKAT_SUB_SUB_KELOMPOK = 5;

    protected $table = 'kodefikasi_barang';

    protected $fillable = ['kode', 'uraian', 'tingkat', 'induk_kode', 'kategori_lokal'];

    protected function casts(): array
    {
        return ['tingkat' => 'integer'];
    }
}
