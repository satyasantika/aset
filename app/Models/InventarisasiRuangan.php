<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use App\Enums\StatusInventarisasiRuangan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu ruangan dalam satu periode inventarisasi.
 *
 * @property StatusInventarisasiRuangan $status
 */
class InventarisasiRuangan extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'inventarisasi_ruangan';

    protected $attributes = ['status' => 'belum'];

    protected $fillable = ['periode_id', 'ruangan_id', 'petugas_id', 'status', 'selesai_pada'];

    protected function casts(): array
    {
        return ['status' => StatusInventarisasiRuangan::class, 'selesai_pada' => 'datetime'];
    }

    /** @return BelongsTo<PeriodeInventarisasi, $this> */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeInventarisasi::class, 'periode_id');
    }

    /** @return BelongsTo<Ruangan, $this> */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class)->withTrashed();
    }

    /** @return BelongsToMany<User, $this> */
    public function petugas(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'inventarisasi_petugas', 'inventarisasi_ruangan_id', 'user_id');
    }

    /** @return HasMany<HasilInventarisasi, $this> */
    public function hasil(): HasMany
    {
        return $this->hasMany(HasilInventarisasi::class, 'inventarisasi_ruangan_id');
    }
}
