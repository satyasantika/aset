<?php

namespace App\Notifications;

use App\Filament\Resources\Inventarisasi\PeriodeInventarisasiResource;
use App\Models\PeriodeInventarisasi;

class BeritaAcaraMenungguPengesahan extends NotifikasiSiman
{
    public function __construct(public readonly PeriodeInventarisasi $periode)
    {
        parent::__construct();
    }

    public function judul(): string
    {
        return 'Berita acara inventarisasi menunggu pengesahan';
    }

    public function isi(): string
    {
        return "Periode \"{$this->periode->nama}\" telah ditutup; berita acara menunggu pengesahan.";
    }

    public function url(): string
    {
        return PeriodeInventarisasiResource::getUrl('view', ['record' => $this->periode]);
    }
}
