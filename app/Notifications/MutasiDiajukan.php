<?php

namespace App\Notifications;

use App\Filament\Resources\Mutasi\MutasiResource;
use App\Models\Mutasi;

class MutasiDiajukan extends NotifikasiSiman
{
    public function __construct(public readonly Mutasi $mutasi)
    {
        parent::__construct();
    }

    public function judul(): string
    {
        return 'Pengajuan mutasi baru';
    }

    public function isi(): string
    {
        return "Mutasi {$this->mutasi->nomor} dari {$this->mutasi->asal->nama} ke {$this->mutasi->tujuan->nama} menunggu keputusan.";
    }

    public function url(): string
    {
        return MutasiResource::getUrl('view', ['record' => $this->mutasi]);
    }
}
