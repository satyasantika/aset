<?php

namespace App\Livewire;

use App\Actions\Peminjaman\BatalkanPeminjaman;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Riwayat peminjaman milik sendiri (BR-23: pemilik selalu berhak melihat data pribadinya).
 *
 * @property-read Collection<int, Peminjaman> $daftar
 */
#[Layout('components.layouts.lapangan')]
#[Title('Pinjaman saya')]
class PinjamanSaya extends Component
{
    public function batalkan(string $id): void
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $peminjaman = Peminjaman::query()->where('peminjam_user_id', $pengguna->getKey())->findOrFail($id);

        app(BatalkanPeminjaman::class)->handle($peminjaman, $pengguna);
        unset($this->daftar);
    }

    /** @return Collection<int, Peminjaman> */
    #[Computed]
    public function daftar(): Collection
    {
        return Peminjaman::query()->with('item.aset')->where('peminjam_user_id', auth()->id())->latest()->limit(50)->get();
    }

    public function render(): View
    {
        return view('livewire.pinjaman-saya');
    }
}
