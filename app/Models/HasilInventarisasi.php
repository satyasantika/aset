<?php

namespace App\Models;

use App\Concerns\MemilikiTautanBerkas;
use App\Enums\HasilInventarisasi as Hasil;
use App\Enums\KondisiAset;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil pemeriksaan satu aset (atau temuan berlebih tanpa aset) pada satu ruangan-periode.
 *
 * @property Hasil $hasil
 * @property KondisiAset|null $kondisi_ditemukan
 */
class HasilInventarisasi extends Model
{
    use HasUuids, MemilikiTautanBerkas;

    protected $table = 'hasil_inventarisasi';

    protected $fillable = ['inventarisasi_ruangan_id', 'aset_id', 'hasil', 'kondisi_ditemukan', 'deskripsi_temuan', 'dipindai_oleh', 'dipindai_pada'];

    protected function casts(): array
    {
        return ['hasil' => Hasil::class, 'kondisi_ditemukan' => KondisiAset::class, 'dipindai_pada' => 'datetime'];
    }

    /** @return BelongsTo<InventarisasiRuangan, $this> */
    public function inventarisasiRuangan(): BelongsTo
    {
        return $this->belongsTo(InventarisasiRuangan::class, 'inventarisasi_ruangan_id');
    }

    /** @return BelongsTo<Aset, $this> */
    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function pemindai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dipindai_oleh');
    }
}
