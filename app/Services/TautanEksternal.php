<?php

namespace App\Services;

use App\Contracts\PenyimpananBerkas;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Penyimpanan berkas berupa tautan eksternal (Google Drive dsb.). Tidak pernah menyimpan isi berkas. */
class TautanEksternal implements PenyimpananBerkas
{
    public function tambah(Model $pemilik, string $jenis, string $label, string $url, ?User $oleh = null): TautanBerkas
    {
        $this->validasi($url);

        $tautan = $pemilik->morphMany(TautanBerkas::class, 'pemilik')->create([
            'jenis' => $jenis,
            'label' => $label,
            'url' => $url,
            'ditambahkan_oleh' => ($oleh ?? auth()->user())?->getKey(),
        ]);

        PeriksaTautanBerkas::dispatch($tautan->getKey());

        return $tautan;
    }

    public function daftar(Model $pemilik, ?string $jenis = null): Collection
    {
        return $pemilik->morphMany(TautanBerkas::class, 'pemilik')
            ->when($jenis, fn ($q) => $q->where('jenis', $jenis))
            ->orderBy('created_at')
            ->get();
    }

    public function perbarui(TautanBerkas $tautan, string $url, ?string $label = null): TautanBerkas
    {
        $this->validasi($url);

        $tautan->fill(['url' => $url, 'label' => $label ?? $tautan->label])->save();
        PeriksaTautanBerkas::dispatch($tautan->getKey());

        return $tautan;
    }

    public function hapus(TautanBerkas $tautan): void
    {
        $tautan->delete();
    }

    private function validasi(string $url): void
    {
        $v = Validator::make(['url' => $url], ['url' => [new TautanBerkasValid]]);

        if ($v->fails()) {
            throw new ValidationException($v);
        }
    }
}
