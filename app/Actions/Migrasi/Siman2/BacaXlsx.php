<?php

namespace App\Actions\Migrasi\Siman2;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use Spatie\SimpleExcel\SimpleExcelReader;

/** Membaca satu sheet XLSX SIMAN-2 sebagai baris asosiatif (judul kolom = nama field GAS). */
class BacaXlsx
{
    public function __construct(private readonly string $berkas)
    {
        if (! is_file($berkas)) {
            throw new RuntimeException("Berkas tidak ditemukan: {$berkas}");
        }
    }

    /** @return list<string> */
    public function namaSheet(): array
    {
        return $this->pembaca()->getSheetNames();
    }

    public function adaSheet(string $nama): bool
    {
        return in_array($nama, $this->namaSheet(), true);
    }

    /** @return LazyCollection<int, array<string, mixed>> */
    public function baris(string $sheet): LazyCollection
    {
        if (! $this->adaSheet($sheet)) {
            return LazyCollection::make([]);
        }

        return $this->pembaca()->fromSheetName($sheet)->trimHeaderRow()->getRows()
            ->filter(fn (array $baris) => collect($baris)->contains(fn ($v) => $v !== null && $v !== ''));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function semua(string $sheet): Collection
    {
        return collect($this->baris($sheet)->all());
    }

    private function pembaca(): SimpleExcelReader
    {
        return SimpleExcelReader::create($this->berkas, 'xlsx');
    }
}
