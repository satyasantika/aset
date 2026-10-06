<?php

namespace App\Actions\Migrasi\Siman2;

/** Satu langkah impor (satu sheet SIMAN-2). */
interface Importer
{
    /** Nama langkah untuk laporan. */
    public function nama(): string;

    public function jalankan(Konteks $konteks, BacaXlsx $xlsx): void;
}
