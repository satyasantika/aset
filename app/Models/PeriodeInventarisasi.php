<?php

namespace App\Models;

use App\Concerns\MemilikiTautanBerkas;
use App\Concerns\TercatatAktivitas;
use App\Enums\JenisInventarisasi;
use App\Enums\StatusPeriodeInventarisasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Periode sensus/opname (RG-04, BR-14). Hanya satu periode `berjalan` pada satu waktu.
 *
 * @property StatusPeriodeInventarisasi $status
 * @property JenisInventarisasi $jenis
 * @property array<string, mixed>|null $berita_acara
 */
class PeriodeInventarisasi extends Model
{
    use HasUuids, MemilikiTautanBerkas, TercatatAktivitas;

    protected $table = 'periode_inventarisasi';

    protected $attributes = ['status' => 'rencana', 'jenis' => 'sensus'];

    protected $fillable = [
        'nama', 'jenis', 'mulai', 'selesai_rencana', 'status', 'dibuka_pada', 'ditutup_pada', 'berita_acara', 'disahkan_oleh', 'disahkan_pada',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisInventarisasi::class,
            'status' => StatusPeriodeInventarisasi::class,
            'mulai' => 'date',
            'selesai_rencana' => 'date',
            'dibuka_pada' => 'datetime',
            'ditutup_pada' => 'datetime',
            'disahkan_pada' => 'datetime',
            'berita_acara' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function atributRahasia(): array
    {
        return ['berita_acara'];
    }

    /** @return HasMany<InventarisasiRuangan, $this> */
    public function ruangan(): HasMany
    {
        return $this->hasMany(InventarisasiRuangan::class, 'periode_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pengesah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_oleh');
    }

    /**
     * @param  Builder<PeriodeInventarisasi>  $query
     * @return Builder<PeriodeInventarisasi>
     */
    public function scopeBerjalan(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), StatusPeriodeInventarisasi::Berjalan->value);
    }
}
