<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsulanPenghapusanItem extends Model
{
    use HasUuids;

    public const RUSAK_BERAT = 'rusak_berat';

    public const HILANG = 'hilang';

    protected $table = 'usulan_penghapusan_item';

    protected $fillable = ['usulan_id', 'aset_id', 'alasan_item'];

    /** @return BelongsTo<UsulanPenghapusan, $this> */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(UsulanPenghapusan::class, 'usulan_id');
    }

    /** @return BelongsTo<Aset, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class)->withTrashed();
    }
}
