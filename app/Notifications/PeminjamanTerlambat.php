<?php

namespace App\Notifications;

use App\Filament\Resources\Peminjaman\PeminjamanResource;
use App\Models\Peminjaman;

class PeminjamanTerlambat extends NotifikasiSiman
{
    public function __construct(public readonly Peminjaman $peminjaman)
    {
        parent::__construct();
    }

    public function judul(): string
    {
        return 'Peminjaman melewati batas pengembalian';
    }

    public function isi(): string
    {
        return "Peminjaman {$this->peminjaman->nomor} seharusnya dikembalikan pada {$this->peminjaman->rencana_kembali->translatedFormat('j F Y H:i')}. Mohon segera dikembalikan.";
    }

    public function url(): string
    {
        return PeminjamanResource::getUrl('view', ['record' => $this->peminjaman]);
    }

    protected function pakaiWhatsapp(): bool
    {
        return true;
    }
}
