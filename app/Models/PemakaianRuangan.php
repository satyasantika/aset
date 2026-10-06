<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pemakaian ruangan terjadwal (integrasi Surat/OrmawaHub): satu sumber jadwal agar tidak bentrok.
 *
 * @property string $id
 * @property string $ruangan_id
 * @property Carbon $mulai
 * @property Carbon $selesai
 * @property string $kegiatan
 * @property string $sumber
 * @property string|null $referensi_eksternal
 * @property string $status
 */
class PemakaianRuangan extends Model
{
    use HasUuids, TercatatAktivitas;

    public const STATUS_TERJADWAL = 'terjadwal';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    public const SUMBER_SURAT = 'surat';

    public const SUMBER_MANUAL = 'manual';

    protected $table = 'pemakaian_ruangan';

    protected $fillable = ['ruangan_id', 'mulai', 'selesai', 'kegiatan', 'sumber', 'referensi_eksternal', 'status', 'dicatat_oleh'];

    protected function casts(): array
    {
        return ['mulai' => 'datetime', 'selesai' => 'datetime'];
    }

    /** @return BelongsTo<Ruangan, $this> */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }

    /**
     * Terjadwal yang beririsan dengan rentang [$mulai, $selesai) (tepi bersentuhan tidak dianggap bentrok).
     *
     * @param  Builder<PemakaianRuangan>  $query
     * @return Builder<PemakaianRuangan>
     */
    public function scopeBeririsan(Builder $query, \DateTimeInterface $mulai, \DateTimeInterface $selesai): Builder
    {
        return $query->where('status', self::STATUS_TERJADWAL)->where('mulai', '<', $selesai)->where('selesai', '>', $mulai);
    }
}
