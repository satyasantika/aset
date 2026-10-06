<?php

namespace App\Models;

use App\Concerns\TercatatAktivitas;
use App\Enums\PenyediaBerkas;
use App\Enums\StatusCekTautan;
use App\Support\TautanBerkasParser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property PenyediaBerkas $penyedia
 * @property StatusCekTautan $status_cek
 */
class TautanBerkas extends Model
{
    use HasUuids, SoftDeletes, TercatatAktivitas;

    protected $table = 'tautan_berkas';

    protected $attributes = ['penyedia' => 'lainnya', 'status_cek' => 'belum'];

    protected $fillable = ['pemilik_type', 'pemilik_id', 'jenis', 'label', 'url', 'penyedia', 'drive_file_id', 'status_cek', 'dicek_pada', 'ditambahkan_oleh'];

    protected function casts(): array
    {
        return [
            'penyedia' => PenyediaBerkas::class,
            'status_cek' => StatusCekTautan::class,
            'dicek_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TautanBerkas $tautan) {
            if ($tautan->isDirty('url')) {
                $tautan->penyedia = TautanBerkasParser::penyedia($tautan->url);
                $tautan->drive_file_id = TautanBerkasParser::idDrive($tautan->url);

                if ($tautan->exists) {
                    // URL diganti: hasil pemeriksaan lama tidak berlaku lagi.
                    $tautan->status_cek = StatusCekTautan::Belum;
                    $tautan->dicek_pada = null;
                }
            }
        });
    }

    /** @return MorphTo<Model, $this> */
    public function pemilik(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function penambah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditambahkan_oleh');
    }
}
