<?php

namespace App\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Mencatat perubahan model ke activitylog (BR-21). Pelaku selalu pengguna terautentikasi (`auth()->user()`),
 * tidak pernah dari input. Hanya atribut yang berubah dan tidak pernah menyimpan rahasia.
 */
trait TercatatAktivitas
{
    use LogsActivity;

    /** @return list<string> */
    protected static function atributRahasia(): array
    {
        return ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];
    }

    /** Nama modul untuk log; bawaan: nama kelas model dalam huruf kecil. */
    public static function namaModulAudit(): string
    {
        return strtolower(class_basename(static::class));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(static::atributRahasia())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(static::namaModulAudit());
    }
}
