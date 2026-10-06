<?php

namespace App\Notifications;

use App\Filament\Resources\Dbr\DbrResource;
use App\Models\DbrVersi;

class DbrMenungguPengesahan extends NotifikasiSiman
{
    public function __construct(public readonly DbrVersi $dbr)
    {
        parent::__construct();
    }

    public function judul(): string
    {
        return 'DBR menunggu pengesahan';
    }

    public function isi(): string
    {
        return "{$this->dbr->judul()} versi {$this->dbr->versi} telah disetujui PIC dan menunggu pengesahan.";
    }

    public function url(): string
    {
        return DbrResource::getUrl('view', ['record' => $this->dbr]);
    }
}
