<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use App\Enums\JenisPeminjam;
use App\Enums\StatusPeminjaman;
use Database\Factories\PeminjamanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Peminjaman internal (BR-07..BR-11). Status `dipinjam` aset dihitung dari baris ini (BR-04), bukan disimpan di aset.
 *
 * @property StatusPeminjaman $status
 * @property JenisPeminjam $jenis_peminjam
 * @property Carbon $mulai
 * @property Carbon $rencana_kembali
 */
class Peminjaman extends Model
{
    /** @use HasFactory<PeminjamanFactory> */
    use HasFactory, HasUuids, TercatatAktivitas;

    protected $table = 'peminjaman';

    protected $fillable = [
        'nomor', 'jenis_peminjam', 'peminjam_user_id', 'nama_peminjam', 'kontak_peminjam', 'unit_peminjam', 'keperluan',
        'mulai', 'rencana_kembali', 'status', 'diputuskan_oleh', 'diputuskan_pada', 'catatan_keputusan',
        'diserahkan_oleh', 'diserahkan_pada', 'diterima_kembali_oleh', 'dikembalikan_pada', 'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'jenis_peminjam' => JenisPeminjam::class,
            'status' => StatusPeminjaman::class,
            'mulai' => 'datetime',
            'rencana_kembali' => 'datetime',
            'diputuskan_pada' => 'datetime',
            'diserahkan_pada' => 'datetime',
            'dikembalikan_pada' => 'datetime',
        ];
    }

    /** @return HasMany<PeminjamanItem, $this> */
    public function item(): HasMany
    {
        return $this->hasMany(PeminjamanItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function peminjamUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'peminjam_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    /**
     * Peminjaman yang boleh dilihat pengguna (BR-23): admin semua; pejabat-penatausahaan permohonan pihak luar;
     * PIC yang memuat aset di ruangannya; selain itu hanya miliknya sendiri.
     *
     * @param  Builder<Peminjaman>  $query
     * @return Builder<Peminjaman>
     */
    public function scopeTerlihatOleh(Builder $query, User $pengguna): Builder
    {
        if ($pengguna->hasAnyRole(['super-admin', 'admin-bmn'])) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($pengguna) {
            $q->where('peminjam_user_id', $pengguna->getKey());

            if ($pengguna->hasRole('pejabat-penatausahaan')) {
                $q->orWhere('jenis_peminjam', JenisPeminjam::PihakLuar->value);
            }

            if ($pengguna->hasRole('pic-ruangan')) {
                $q->orWhereIn('peminjaman.id', DB::table('peminjaman_item')
                    ->join('aset', 'aset.id', '=', 'peminjaman_item.aset_id')
                    ->join('ruangan_pic', 'ruangan_pic.ruangan_id', '=', 'aset.ruangan_id')
                    ->where('ruangan_pic.user_id', $pengguna->getKey())
                    ->select('peminjaman_item.peminjaman_id'));
            }
        });
    }

    /** Terlambat dihitung, bukan status (BR-09): `dipinjam` dan rencana kembali sudah lewat. */
    public function terlambat(): bool
    {
        return $this->status === StatusPeminjaman::Dipinjam && $this->rencana_kembali->isPast();
    }
}
