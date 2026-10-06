<?php

namespace App\Models;

use App\Concerns\MembersihkanCacheMaster;
use App\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Ruangan extends Model
{
    use HasUuids, MembersihkanCacheMaster, SoftDeletes, TercatatAktivitas;

    protected $table = 'ruangan';

    protected $fillable = [
        'kode', 'nama', 'gedung_id', 'kategori_ruangan_id', 'lantai', 'kapasitas',
        'dapat_dipinjam', 'luas_m2', 'k3l', 'keterangan',
    ];

    /** Peran yang mengelola seluruh ruangan (tanpa pembatasan penugasan PIC). */
    public const PERAN_SEMUA_RUANGAN = ['super-admin', 'admin-bmn'];

    protected function casts(): array
    {
        return [
            'dapat_dipinjam' => 'boolean',
            'luas_m2' => 'decimal:2',
            'k3l' => 'array',
        ];
    }

    /** @return BelongsTo<Gedung, $this> */
    public function gedung(): BelongsTo
    {
        return $this->belongsTo(Gedung::class);
    }

    /** @return BelongsTo<KategoriRuangan, $this> */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriRuangan::class, 'kategori_ruangan_id');
    }

    /** @return BelongsToMany<Prodi, $this> */
    public function prodi(): BelongsToMany
    {
        return $this->belongsToMany(Prodi::class, 'prodi_ruangan');
    }

    /** @return BelongsToMany<User, $this> */
    public function pic(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ruangan_pic', 'ruangan_id', 'user_id')
            ->withPivot('utama')
            ->withTimestamps();
    }

    /**
     * Ruangan yang boleh dikelola pengguna (BR-05): admin penuh; PIC hanya yang ditugaskan; selain itu tidak ada.
     *
     * @param  Builder<Ruangan>  $query
     * @return Builder<Ruangan>
     */
    public function scopeDikelolaOleh(Builder $query, User $pengguna): Builder
    {
        if ($pengguna->hasAnyRole(self::PERAN_SEMUA_RUANGAN)) {
            return $query;
        }

        return $query->whereIn(
            $this->qualifyColumn('id'),
            DB::table('ruangan_pic')->where('user_id', $pengguna->getKey())->select('ruangan_id'),
        );
    }

    /** Jadikan satu PIC sebagai PIC utama; yang lain otomatis bukan utama. */
    public function tetapkanPicUtama(User $pengguna): void
    {
        DB::transaction(function () use ($pengguna) {
            $this->pic()->newPivotStatement()->where('ruangan_id', $this->getKey())->update(['utama' => false]);
            $this->pic()->updateExistingPivot($pengguna->getKey(), ['utama' => true]);
        });
    }
}
