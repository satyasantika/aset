<?php

namespace App\Notifications;

use App\Filament\Resources\Inventarisasi\PeriodeInventarisasiResource;

/** BR-15: inventarisasi terakhir yang disahkan mendekati/melewati batas aturan. */
class PengingatInventarisasi extends NotifikasiSiman
{
    public function __construct(public readonly string $pesan)
    {
        parent::__construct();
    }

    public function judul(): string
    {
        return 'Pengingat inventarisasi BMN';
    }

    public function isi(): string
    {
        return $this->pesan;
    }

    public function url(): string
    {
        return PeriodeInventarisasiResource::getUrl('index');
    }
}
