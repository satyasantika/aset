<?php

namespace App\Notifications;

use App\Filament\Resources\Peminjaman\PeminjamanResource;
use App\Models\Peminjaman;

class PengingatPengambilan extends NotifikasiSiman
{
    public function __construct(public readonly Peminjaman $peminjaman)
    {
        parent::__construct();
    }

    public function judul(): string
    {
        return 'Pengingat pengambilan barang';
    }

    public function isi(): string
    {
        return "Peminjaman {$this->peminjaman->nomor} disetujui dan mulai {$this->peminjaman->mulai->translatedFormat('j F Y H:i')}. Mohon diserahkan/diambil sesuai jadwal.";
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
