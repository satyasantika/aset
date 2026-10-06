<?php

namespace App\Models;

use App\Concerns\MemilikiTautanBerkas;
use App\Concerns\TercatatAktivitas;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusBmn;
use App\Enums\SumberPerolehan;
use Database\Factories\AsetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Satu baris = satu barang fisik (BR-01). Identitas (id, kode+NUP) tidak pernah dinomori ulang.
 *
 * @property StatusBmn $status_bmn
 * @property KondisiAset $kondisi
 * @property StatusAset $status
 * @property SumberPerolehan|null $sumber_perolehan
 */
class Aset extends Model
{
    /** @use HasFactory<AsetFactory> */
    use HasFactory, HasUuids, MemilikiTautanBerkas, SoftDeletes, TercatatAktivitas;

    protected $table = 'aset';

    protected $attributes = [
        'status_bmn' => 'tercatat',
        'kondisi' => 'B',
        'status' => 'aktif',
        'penguasaan' => 'milik_sendiri',
        'dapat_dipinjam' => false,
        'label_perlu_cetak_ulang' => false,
    ];

    protected $fillable = [
        'status_bmn', 'kode_barang', 'nup', 'kode_internal', 'nama', 'merk_tipe', 'spesifikasi',
        'tahun_perolehan', 'tanggal_perolehan', 'nilai_perolehan', 'sumber_perolehan', 'sumber_dana',
        'nomor_dokumen_perolehan', 'penguasaan', 'ruangan_id', 'lokasi_lainnya', 'kondisi', 'status',
        'dapat_dipinjam', 'nomor_sk_penghapusan', 'tanggal_sk_penghapusan', 'dicetak_pada',
        'label_perlu_cetak_ulang', 'kelompok_pengadaan', 'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'status_bmn' => StatusBmn::class,
            'kondisi' => KondisiAset::class,
            'status' => StatusAset::class,
            'sumber_perolehan' => SumberPerolehan::class,
            'nup' => 'integer',
            'tahun_perolehan' => 'integer',
            'tanggal_perolehan' => 'date',
            'tanggal_sk_penghapusan' => 'date',
            'nilai_perolehan' => 'decimal:2',
            'dapat_dipinjam' => 'boolean',
            'label_perlu_cetak_ulang' => 'boolean',
            'dicetak_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Aset $aset) {
            $galat = [];

            if ($aset->status_bmn === StatusBmn::Tercatat && (blank($aset->kode_barang) || blank($aset->nup))) {
                $galat['kode_barang'] = 'Barang tercatat BMN wajib memiliki kode barang dan NUP (BR-01).';
            }

            if ($aset->status_bmn === StatusBmn::BelumTercatat && blank($aset->kode_internal)) {
                $galat['kode_internal'] = 'Barang yang belum tercatat wajib memiliki kode internal (BR-01).';
            }

            if (filled($aset->ruangan_id) === filled($aset->lokasi_lainnya)) {
                $galat['ruangan_id'] = 'Aset harus berada di tepat satu ruangan atau ditandai lokasi lainnya (BR-02).';
            }

            if ($aset->status === StatusAset::Dihapus && (blank($aset->nomor_sk_penghapusan) || blank($aset->tanggal_sk_penghapusan))) {
                $galat['nomor_sk_penghapusan'] = 'Status dihapus wajib disertai nomor dan tanggal SK penghapusan (BR-04).';
            }

            if ($galat !== []) {
                throw ValidationException::withMessages($galat);
            }
        });
    }

    /** @return BelongsTo<Ruangan, $this> */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class);
    }

    /** @return HasMany<RiwayatKondisiAset, $this> */
    public function riwayatKondisi(): HasMany
    {
        return $this->hasMany(RiwayatKondisiAset::class)->latest('created_at')->latest('id');
    }

    /** @return HasMany<RiwayatLokasiAset, $this> */
    public function riwayatLokasi(): HasMany
    {
        return $this->hasMany(RiwayatLokasiAset::class)->latest('created_at')->latest('id');
    }

    /** @return HasMany<RiwayatStatusAset, $this> */
    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(RiwayatStatusAset::class)->latest('created_at')->latest('id');
    }

    /** @return HasMany<LabelLama, $this> */
    public function labelLama(): HasMany
    {
        return $this->hasMany(LabelLama::class);
    }

    /**
     * Status `dipinjam` tidak disimpan (BR-04): dihitung dari peminjaman yang masih berjalan.
     * Tabel peminjaman baru ada sejak fase peminjaman; sebelum itu tidak ada barang yang dipinjam.
     *
     * @return Attribute<bool, never>
     */
    protected function sedangDipinjam(): Attribute
    {
        return Attribute::get(function (): bool {
            if (! Schema::hasTable('peminjaman_item')) {
                return false;
            }

            return DB::table('peminjaman_item')
                ->join('peminjaman', 'peminjaman.id', '=', 'peminjaman_item.peminjaman_id')
                ->where('peminjaman_item.aset_id', $this->getKey())
                ->where('peminjaman.status', 'dipinjam')
                ->exists();
        });
    }

    /**
     * Kode yang tampil di label & dokumen: kode barang + NUP, atau kode internal bila belum tercatat.
     *
     * @return Attribute<string, never>
     */
    protected function kodeTampil(): Attribute
    {
        return Attribute::get(fn (): string => $this->kode_barang && $this->nup
            ? "{$this->kode_barang} / {$this->nup}"
            : (string) $this->kode_internal);
    }

    /**
     * @param  Builder<Aset>  $query
     * @return Builder<Aset>
     */
    public function scopeDiRuangan(Builder $query, Ruangan|string $ruangan): Builder
    {
        return $query->where($this->qualifyColumn('ruangan_id'), $ruangan instanceof Ruangan ? $ruangan->getKey() : $ruangan);
    }

    /**
     * Aset yang secara aturan dapat dipinjam (BR-11): aktif, bukan rusak berat, dan ditandai dapat dipinjam.
     * Keberadaan peminjaman yang bentrok diperiksa terpisah (BR-08) oleh Action peminjaman.
     *
     * @param  Builder<Aset>  $query
     * @return Builder<Aset>
     */
    public function scopeDapatDipinjam(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('status'), StatusAset::Aktif->value)
            ->where($this->qualifyColumn('kondisi'), '!=', KondisiAset::RusakBerat->value)
            ->where($this->qualifyColumn('dapat_dipinjam'), true);
    }

    /** Apakah aset ini (menurut aturan, tanpa melihat peminjaman bentrok) boleh dipinjam. */
    public function memenuhiSyaratPinjam(): bool
    {
        return $this->status === StatusAset::Aktif
            && $this->kondisi !== KondisiAset::RusakBerat
            && $this->dapat_dipinjam;
    }
}
