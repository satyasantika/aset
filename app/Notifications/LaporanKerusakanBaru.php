<?php

namespace App\Notifications;

use App\Filament\Resources\TiketPemeliharaan\TiketPemeliharaanResource;
use App\Models\TiketPemeliharaan;

class LaporanKerusakanBaru extends NotifikasiSiman
{
    public function __construct(public readonly TiketPemeliharaan $tiket)
    {
        parent::__construct();
    }

    public function judul(): string
    {
        return 'Laporan kerusakan baru';
    }

    public function isi(): string
    {
        return "Tiket {$this->tiket->nomor} untuk {$this->tiket->aset->nama} ({$this->tiket->aset->kode_tampil}) perlu ditindaklanjuti.";
    }

    public function url(): string
    {
        return TiketPemeliharaanResource::getUrl('view', ['record' => $this->tiket]);
    }
}
