<?php

namespace App\Concerns;

use App\Support\CacheMaster;
use Illuminate\Database\Eloquent\Model;

/** Membersihkan cache `aset:master:<tabel>` setiap kali model master disimpan, dihapus, atau dipulihkan. */
trait MembersihkanCacheMaster
{
    protected static function bootMembersihkanCacheMaster(): void
    {
        $bersihkan = fn (Model $model) => CacheMaster::bersihkan($model->getTable());

        static::saved($bersihkan);
        static::deleted($bersihkan);

        static::registerModelEvent('restored', $bersihkan);
    }
}
