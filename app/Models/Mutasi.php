<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use App\Enums\StatusMutasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * Mutasi lokasi satu/lebih aset dari satu ruangan asal ke satu ruangan tujuan (BR-06).
 *
 * @property StatusMutasi $status
 */
class Mutasi extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'mutasi';

    protected $fillable = [
        'nomor', 'ruangan_asal_id', 'ruangan_tujuan_id', 'alasan', 'status', 'diajukan_oleh',
        'diputuskan_oleh', 'diputuskan_pada', 'catatan_keputusan',
    ];

    protected $attributes = ['status' => 'diajukan'];

    protected function casts(): array
    {
        return ['status' => StatusMutasi::class, 'diputuskan_pada' => 'datetime'];
    }

    /** @return BelongsTo<Ruangan, $this> */
    public function asal(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_asal_id')->withTrashed();
    }

    /** @return BelongsTo<Ruangan, $this> */
    public function tujuan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_tujuan_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    /** @return BelongsToMany<Aset, $this> */
    public function aset(): BelongsToMany
    {
        return $this->belongsToMany(Aset::class, 'mutasi_item', 'mutasi_id', 'aset_id');
    }

    public function masihDiajukan(): bool
    {
        return $this->status === StatusMutasi::Diajukan;
    }

    /**
     * Mutasi yang berkaitan dengan pengguna: admin semua; selain itu yang melibatkan ruangannya atau diajukannya.
     *
     * @param  Builder<Mutasi>  $query
     * @return Builder<Mutasi>
     */
    public function scopeTerlihatOleh(Builder $query, User $pengguna): Builder
    {
        if ($pengguna->can('mutasi.putuskan')) {
            return $query;
        }

        $ruanganSaya = DB::table('ruangan_pic')->where('user_id', $pengguna->getKey())->select('ruangan_id');

        return $query->where(fn (Builder $q) => $q
            ->where('diajukan_oleh', $pengguna->getKey())
            ->orWhereIn('ruangan_asal_id', $ruanganSaya)
            ->orWhereIn('ruangan_tujuan_id', $ruanganSaya));
    }
}
