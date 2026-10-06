<?php

namespace App\Notifications;

use App\Filament\Resources\Peminjaman\PeminjamanResource;
use App\Models\Peminjaman;

class PengajuanPeminjamanBaru extends NotifikasiSiman
{
    public function __construct(public readonly Peminjaman $peminjaman)
    {
        parent::__construct();
    }

    public function judul(): string
    {
        return 'Pengajuan peminjaman baru';
    }

    public function isi(): string
    {
        return "Pengajuan {$this->peminjaman->nomor} dari {$this->peminjaman->nama_peminjam} menunggu keputusan Anda.";
    }

    public function url(): string
    {
        return PeminjamanResource::getUrl('view', ['record' => $this->peminjaman]);
    }
}
