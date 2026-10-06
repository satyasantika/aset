<?php

namespace App\Models;

use App\Enums\KondisiAset;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property KondisiAset $kondisi_saat_pinjam
 * @property KondisiAset|null $kondisi_saat_kembali
 */
class PeminjamanItem extends Model
{
    use HasUuids;

    protected $table = 'peminjaman_item';

    protected $fillable = ['peminjaman_id', 'aset_id', 'kondisi_saat_pinjam', 'kondisi_saat_kembali', 'catatan'];

    protected function casts(): array
    {
        return ['kondisi_saat_pinjam' => KondisiAset::class, 'kondisi_saat_kembali' => KondisiAset::class];
    }

    /** @return BelongsTo<Peminjaman, $this> */
    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(Peminjaman::class);
    }

    /** @return BelongsTo<Aset, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class);
    }
}
