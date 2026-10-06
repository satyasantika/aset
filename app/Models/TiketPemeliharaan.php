<?php

namespace App\Models;

use App\Concerns\MembersihkanCacheStatistik;
use App\Concerns\MemilikiTautanBerkas;
use App\Concerns\TercatatAktivitas;
use App\Enums\StatusTiket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Tiket pemeliharaan (RG-08, BR-10, BR-12). Data pelapor (nama/kontak/IP-hash) adalah data pribadi (BR-23).
 *
 * @property StatusTiket $status
 */
class TiketPemeliharaan extends Model
{
    use HasUuids, MembersihkanCacheStatistik, MemilikiTautanBerkas, TercatatAktivitas;

    public const SUMBER = ['publik', 'civitas', 'pic', 'peminjaman', 'inventarisasi'];

    protected $table = 'tiket_pemeliharaan';

    protected $attributes = ['status' => 'baru'];

    protected $fillable = [
        'nomor', 'aset_id', 'sumber', 'sumber_id', 'deskripsi', 'nama_pelapor', 'kontak_pelapor', 'pelapor_user_id', 'ip_hash',
        'status', 'tindakan', 'biaya', 'ditangani_oleh', 'selesai_pada',
    ];

    protected function casts(): array
    {
        return ['status' => StatusTiket::class, 'biaya' => 'decimal:2', 'selesai_pada' => 'datetime'];
    }

    /** @return array<int, string> Atribut yang tidak dicatat ke jejak audit (hash IP pelapor). */
    protected static function atributRahasia(): array
    {
        return ['ip_hash'];
    }

    /** @return BelongsTo<Aset, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function penangan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pelaporUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pelapor_user_id');
    }

    /**
     * Tiket yang boleh dilihat pengguna: admin semua; PIC hanya untuk aset di ruangannya (BR-05).
     *
     * @param  Builder<TiketPemeliharaan>  $query
     * @return Builder<TiketPemeliharaan>
     */
    public function scopeTerlihatOleh(Builder $query, User $pengguna): Builder
    {
        if ($pengguna->hasAnyRole(Ruangan::PERAN_SEMUA_RUANGAN)) {
            return $query;
        }

        return $query->whereIn($this->qualifyColumn('aset_id'), DB::table('aset')
            ->join('ruangan_pic', 'ruangan_pic.ruangan_id', '=', 'aset.ruangan_id')
            ->where('ruangan_pic.user_id', $pengguna->getKey())
            ->select('aset.id'));
    }

    /**
     * @param  Builder<TiketPemeliharaan>  $query
     * @return Builder<TiketPemeliharaan>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('status'), array_map(fn (StatusTiket $s) => $s->value, StatusTiket::yangAktif()));
    }
}
