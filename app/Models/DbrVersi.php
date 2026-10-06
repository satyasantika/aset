<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use App\Enums\StatusDbr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Satu versi Daftar Barang Ruangan (DBR) atau Daftar Barang Lainnya (DBL, `ruangan_id` null). Isi dokumen = `snapshot`
 * (BR-13); PDF selalu dirender dari snapshot, bukan data aset saat ini.
 *
 * @property StatusDbr $status
 * @property array<string, mixed> $snapshot
 */
class DbrVersi extends Model
{
    use HasUuids, TercatatAktivitas;

    public const JENIS_DBR = 'dbr';

    public const JENIS_DBL = 'dbl';

    protected $table = 'dbr_versi';

    protected $attributes = ['status' => 'draf', 'jenis' => 'dbr'];

    protected $fillable = [
        'ruangan_id', 'jenis', 'versi', 'status', 'snapshot', 'disetujui_pic_oleh', 'disetujui_pic_pada',
        'disahkan_oleh', 'disahkan_pada', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusDbr::class,
            'snapshot' => 'array',
            'versi' => 'integer',
            'disetujui_pic_pada' => 'datetime',
            'disahkan_pada' => 'datetime',
        ];
    }

    /**
     * Snapshot besar tidak dicatat ulang ke audit; perubahan status/versi cukup.
     *
     * @return list<string>
     */
    protected static function atributRahasia(): array
    {
        return ['snapshot'];
    }

    /** @return BelongsTo<Ruangan, $this> */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function penyetujuPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_pic_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pengesah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_oleh');
    }

    public function adalahDbl(): bool
    {
        return $this->jenis === self::JENIS_DBL;
    }

    public function judul(): string
    {
        return ($this->adalahDbl() ? 'DBL' : 'DBR').' '.($this->snapshot['ruangan']['nama'] ?? 'Barang Lainnya').' v'.$this->versi;
    }

    /**
     * @param  Builder<DbrVersi>  $query
     * @return Builder<DbrVersi>
     */
    public function scopeTerlihatOleh(Builder $query, User $pengguna): Builder
    {
        if ($pengguna->hasAnyRole(['super-admin', 'admin-bmn', 'pejabat-penatausahaan', 'pimpinan'])) {
            return $query;
        }

        return $query->whereIn($this->qualifyColumn('ruangan_id'), DB::table('ruangan_pic')->where('user_id', $pengguna->getKey())->select('ruangan_id'));
    }
}
