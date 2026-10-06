<?php

namespace App\Models;

use App\Actions\Dbr\TandaiDbrPerluDiperbarui;
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

    /** Atribut Aset yang tampil di DBR/DBL; perubahannya membuat versi DBR yang sah menjadi usang (BR-13). */
    public const ATRIBUT_DBR = [
        'ruangan_id', 'lokasi_lainnya', 'kondisi', 'status', 'nama', 'merk_tipe', 'tahun_perolehan',
        'kode_barang', 'nup', 'kode_internal', 'keterangan',
    ];

    protected static function booted(): void
    {
        static::created(fn (Aset $aset) => self::tandaiDbr($aset));

        static::updated(function (Aset $aset) {
            if ($aset->wasChanged(self::ATRIBUT_DBR)) {
                self::tandaiDbr($aset);
            }
        });

        static::deleted(fn (Aset $aset) => self::tandaiDbr($aset));

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

    /** URL publik yang dikodekan di QR label (BR-17): `/a/{id}`. */
    public function urlPublik(): string
    {
        return url('/a/'.$this->getKey());
    }

    /** Menandai DBR/DBL lokasi sekarang dan lokasi sebelumnya `perlu_diperbarui` (BR-13). */
    private static function tandaiDbr(self $aset): void
    {
        $tanda = app(TandaiDbrPerluDiperbarui::class);

        $ruangan = collect([$aset->ruangan_id, $aset->wasChanged('ruangan_id') ? $aset->getOriginal('ruangan_id') : null])->filter()->unique();

        foreach ($ruangan as $id) {
            $tanda->handle((string) $id);
        }

        if (filled($aset->lokasi_lainnya) || ($aset->wasChanged('lokasi_lainnya') && filled($aset->getOriginal('lokasi_lainnya')))) {
            $tanda->handle(null);
        }
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
     *
     * @return Attribute<bool, never>
     */
    protected function sedangDipinjam(): Attribute
    {
        return Attribute::get(function (): bool {
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

    /**
     * Aset yang boleh ditindak pengguna (BR-05): admin semua; PIC hanya yang berada di ruangan yang ditugaskan.
     *
     * @param  Builder<Aset>  $query
     * @return Builder<Aset>
     */
    public function scopeDikelolaOleh(Builder $query, User $pengguna): Builder
    {
        if ($pengguna->hasAnyRole(Ruangan::PERAN_SEMUA_RUANGAN)) {
            return $query;
        }

        return $query->whereIn(
            $this->qualifyColumn('ruangan_id'),
            DB::table('ruangan_pic')->where('user_id', $pengguna->getKey())->select('ruangan_id'),
        );
    }

    /** Apakah aset ini (menurut aturan, tanpa melihat peminjaman bentrok) boleh dipinjam. */
    public function memenuhiSyaratPinjam(): bool
    {
        return $this->status === StatusAset::Aktif
            && $this->kondisi !== KondisiAset::RusakBerat
            && $this->dapat_dipinjam;
    }
}
