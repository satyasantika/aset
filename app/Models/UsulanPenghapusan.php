<?php

namespace App\Models;

use App\Concerns\MemilikiTautanBerkas;
use App\Concerns\TercatatAktivitas;
use App\Enums\StatusUsulanHapus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Usulan penghapusan aset Rusak Berat/hilang (RG-03, RG-09, BR-16). Aset tidak pernah dihapus permanen: setelah SK
 * terbit status aset menjadi `dihapus` dengan nomor & tanggal SK.
 *
 * @property StatusUsulanHapus $status
 * @property Carbon|null $tanggal_sk
 */
class UsulanPenghapusan extends Model
{
    use HasUuids, MemilikiTautanBerkas, TercatatAktivitas;

    protected $table = 'usulan_penghapusan';

    protected $attributes = ['status' => 'draf'];

    protected $fillable = ['nomor', 'alasan', 'status', 'nomor_sk', 'tanggal_sk', 'pengusul_id', 'pemutus_id', 'diputuskan_pada', 'catatan_keputusan'];

    protected function casts(): array
    {
        return ['status' => StatusUsulanHapus::class, 'tanggal_sk' => 'date', 'diputuskan_pada' => 'datetime'];
    }

    /** @return HasMany<UsulanPenghapusanItem, $this> */
    public function item(): HasMany
    {
        return $this->hasMany(UsulanPenghapusanItem::class, 'usulan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pengusul(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengusul_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pemutus_id');
    }

    /**
     * @param  Builder<UsulanPenghapusan>  $query
     * @return Builder<UsulanPenghapusan>
     */
    public function scopeTerbuka(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('status'), [StatusUsulanHapus::Draf->value, StatusUsulanHapus::Diajukan->value, StatusUsulanHapus::DisetujuiInternal->value]);
    }
}
