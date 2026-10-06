<?php

namespace App\Notifications;

use App\Filament\Resources\Mutasi\MutasiResource;
use App\Models\Mutasi;

class MutasiDiputuskan extends NotifikasiSiman
{
    public function __construct(public readonly Mutasi $mutasi)
    {
        parent::__construct();
    }

    public function judul(): string
    {
        return 'Keputusan mutasi';
    }

    public function isi(): string
    {
        return "Mutasi {$this->mutasi->nomor} ({$this->mutasi->asal->nama} → {$this->mutasi->tujuan->nama}) {$this->mutasi->status->label()}.";
    }

    public function url(): string
    {
        return MutasiResource::getUrl('view', ['record' => $this->mutasi]);
    }
}
