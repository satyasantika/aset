<?php

namespace App\Livewire;

use App\Actions\Peminjaman\AjukanPeminjaman;
use App\Actions\Peminjaman\CekKetersediaan;
use App\Models\Aset;
use App\Models\KodefikasiBarang;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Katalog & pengajuan peminjaman civitas (US-PJM-02). Katalog hanya memuat field putih (tanpa data pribadi, nilai,
 * PIC). Pihak luar bukan pilihan di sini (BR-07).
 *
 * @property-read Collection<int, Aset> $katalog
 * @property-read Collection<int, Ruangan> $ruanganKatalog
 */
#[Layout('components.layouts.lapangan')]
#[Title('Pinjam barang')]
class AjukanPinjam extends Component
{
    public string $cari = '';

    public ?string $ruanganId = null;

    public string $mulai = '';

    public string $selesai = '';

    public string $keperluan = '';

    public string $unit = '';

    /** @var list<string> */
    public array $dipilih = [];

    public ?string $nomorBerhasil = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('peminjaman.ajukan'), 403);

        $this->mulai = now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i');
        $this->selesai = now()->addDay()->setTime(16, 0)->format('Y-m-d\TH:i');
    }

    public function ajukan(): void
    {
        $this->validate([
            'keperluan' => ['required', 'string', 'max:1000'],
            'mulai' => ['required', 'date'],
            'selesai' => ['required', 'date'],
            'dipilih' => ['required', 'array', 'min:1'],
        ], ['dipilih.required' => 'Pilih minimal satu barang.', 'dipilih.min' => 'Pilih minimal satu barang.']);

        /** @var User $pemohon */
        $pemohon = auth()->user();

        /** @var Peminjaman $peminjaman */
        $peminjaman = app(AjukanPeminjaman::class)->handle($this->dipilih, $this->keperluan, Carbon::parse($this->mulai), Carbon::parse($this->selesai), $pemohon, $this->unit ?: null);

        $this->nomorBerhasil = $peminjaman->nomor;
        $this->reset('dipilih', 'keperluan', 'unit');
    }

    /** Alasan barang tidak tersedia pada rentang yang dipilih, atau null. */
    public function alasanTidakTersedia(Aset $aset): ?string
    {
        try {
            return app(CekKetersediaan::class)->alasan($aset, Carbon::parse($this->mulai), Carbon::parse($this->selesai));
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return Collection<int, Aset> */
    #[Computed]
    public function katalog(): Collection
    {
        $kata = trim($this->cari);

        return Aset::query()->with('ruangan')->dapatDipinjam()
            ->when($this->ruanganId, fn ($q) => $q->diRuangan($this->ruanganId))
            ->when($kata !== '', fn ($q) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$kata}%")->orWhere('merk_tipe', 'like', "%{$kata}%")))
            ->orderBy('nama')->limit(30)->get();
    }

    /** @return Collection<int, Ruangan> */
    #[Computed]
    public function ruanganKatalog(): Collection
    {
        return Ruangan::query()->with('gedung')->where('dapat_dipinjam', true)->orderBy('nama')->get();
    }

    public function kategori(Aset $aset): ?string
    {
        if ($aset->kode_barang === null) {
            return null;
        }

        $k = KodefikasiBarang::query()->where('kode', $aset->kode_barang)->first(['uraian', 'kategori_lokal']);

        return $k?->kategori_lokal ?: $k?->uraian;
    }

    public function render(): View
    {
        return view('livewire.ajukan-pinjam');
    }
}
