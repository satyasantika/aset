<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Teks label/QR lama SIMAN-2 (ter-normalisasi uppercase) yang diresolusi ke aset baru (BR-17). */
class LabelLama extends Model
{
    use HasUuids;

    protected $table = 'label_lama';

    protected $fillable = ['teks', 'aset_id'];

    /** @return BelongsTo<Aset, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class);
    }
}
