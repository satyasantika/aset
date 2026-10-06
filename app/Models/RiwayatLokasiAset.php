<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat tidak dapat diubah (append-only); hanya memiliki created_at. */
class RiwayatLokasiAset extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'riwayat_lokasi_aset';

    protected $fillable = ['aset_id', 'dari_ruangan_id', 'ke_ruangan_id', 'sumber', 'mutasi_id', 'oleh'];

    /** @return BelongsTo<Aset, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
