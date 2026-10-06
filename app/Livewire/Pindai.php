<?php

namespace App\Livewire;

use App\Actions\Aset\UbahKondisiAset;
use App\Actions\Label\ResolusiLabel;
use App\Enums\KondisiAset;
use App\Models\Aset;
use App\Models\KodefikasiBarang;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Aset|null $aset
 * @property-read string|null $kategori
 *
 * Pemindai QR (login) untuk ponsel: menerima URL baru `/a/{id}` atau teks label lama, menampilkan aset dan aksi
 * sesuai peran. Seluruh otorisasi lewat Policy/Action (tidak di tampilan).
 */
#[Layout('components.layouts.lapangan')]
#[Title('Pindai QR')]
class Pindai extends Component
{
    public string $teks = '';

    public ?string $asetId = null;

    public ?string $pesan = null;

    public ?string $kondisiBaru = null;

    public function cari(ResolusiLabel $resolusi): void
    {
        $this->validate(['teks' => ['required', 'string', 'max:500']]);

        $aset = $resolusi->handle($this->teks);

        $this->asetId = $aset?->getKey();
        $this->pesan = $aset === null ? 'Data untuk kode "'.e(mb_strimwidth($this->teks, 0, 80, '…')).'" tidak ditemukan.' : null;
        $this->kondisiBaru = null;
    }

    public function ubahKondisi(string $kondisi): void
    {
        $aset = $this->aset;
        abort_if($aset === null, 404);

        /** @var User $pelaku */
        $pelaku = auth()->user();

        app(UbahKondisiAset::class)->handle($aset, KondisiAset::from($kondisi), $pelaku, 'manual', null, 'Diubah lewat pemindai QR');

        unset($this->aset);
        $this->kondisiBaru = $kondisi;
    }

    public function ulang(): void
    {
        $this->reset('teks', 'asetId', 'pesan', 'kondisiBaru');
    }

    #[Computed]
    public function aset(): ?Aset
    {
        return $this->asetId ? Aset::query()->with('ruangan')->find($this->asetId) : null;
    }

    #[Computed]
    public function kategori(): ?string
    {
        $aset = $this->aset;

        if ($aset?->kode_barang === null) {
            return null;
        }

        $k = KodefikasiBarang::query()->where('kode', $aset->kode_barang)->first(['uraian', 'kategori_lokal']);

        return $k?->kategori_lokal ?: $k?->uraian;
    }

    public function render(): View
    {
        return view('livewire.pindai');
    }
}
