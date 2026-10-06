<?php

namespace App\Concerns;

use App\Models\TautanBerkas;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Model yang dapat memiliki tautan berkas (foto, SK, bukti, dst.). */
trait MemilikiTautanBerkas
{
    /** @return MorphMany<TautanBerkas, $this> */
    public function tautanBerkas(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }
}
