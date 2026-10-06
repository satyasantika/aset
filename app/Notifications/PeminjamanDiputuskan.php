<?php

namespace App\Notifications;

use App\Filament\Resources\Peminjaman\PeminjamanResource;
use App\Models\Peminjaman;

class PeminjamanDiputuskan extends NotifikasiSiman
{
    public function __construct(public readonly Peminjaman $peminjaman)
    {
        parent::__construct();
    }

    public function judul(): string
    {
        return 'Keputusan peminjaman';
    }

    public function isi(): string
    {
        return "Pengajuan {$this->peminjaman->nomor} {$this->peminjaman->status->label()}."."{$this->catatan()}";
    }

    public function url(): string
    {
        return PeminjamanResource::getUrl('view', ['record' => $this->peminjaman]);
    }

    private function catatan(): string
    {
        return filled($this->peminjaman->catatan_keputusan) ? ' Catatan: '.$this->peminjaman->catatan_keputusan : '';
    }
}
