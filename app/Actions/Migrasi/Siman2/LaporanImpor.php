<?php

namespace App\Actions\Migrasi\Siman2;

/** Ringkasan satu kali jalan impor: hitungan per sheet, galat (menghentikan baris), dan peringatan (data tetap masuk). */
class LaporanImpor
{
    /** @var array<string, array{ok: int, galat: int, dilewati: int}> */
    public array $hitungan = [];

    /** @var list<array{sheet: string, id: string, pesan: string}> */
    public array $galat = [];

    /** @var list<array{sheet: string, id: string, pesan: string}> */
    public array $peringatan = [];

    public function hitung(string $sheet, string $status): void
    {
        $this->hitungan[$sheet] ??= ['ok' => 0, 'galat' => 0, 'dilewati' => 0];
        $this->hitungan[$sheet][$status]++;
    }

    public function tambahGalat(string $sheet, string $id, string $pesan): void
    {
        $this->galat[] = ['sheet' => $sheet, 'id' => $id, 'pesan' => $pesan];
        $this->hitung($sheet, 'galat');
    }

    public function tambahPeringatan(string $sheet, string $id, string $pesan): void
    {
        $this->peringatan[] = ['sheet' => $sheet, 'id' => $id, 'pesan' => $pesan];
    }

    public function adaGalat(): bool
    {
        return $this->galat !== [];
    }
}
