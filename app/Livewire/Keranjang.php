<?php

namespace App\Livewire;

use App\Actions\Label\ResolusiLabel;
use App\Actions\Peminjaman\CatatPeminjamanLangsung;
use App\Actions\Peminjaman\CekKetersediaan;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\User;
use App\Support\Pengaturan;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Keranjang peminjaman PIC (US-PJM-01): tambah aset lewat pindai/cari (hanya ruangan PIC), data peminjam,
 * keperluan, rencana kembali (≤ maks_hari_pinjam). Pencatatan lewat Action CatatPeminjamanLangsung.
 *
 * @property-read Collection<int, Aset> $isiKeranjang
 * @property-read Collection<int, Aset> $hasilCari
 * @property-read Collection<int, User> $hasilPeminjam
 */
#[Layout('components.layouts.lapangan')]
#[Title('Keranjang peminjaman')]
class Keranjang extends Component
{
    /** @var list<string> */
    public array $daftar = [];

    public string $cari = '';

    public string $teksPindai = '';

    public string $modePeminjam = 'akun';

    public string $cariPeminjam = '';

    public ?string $peminjamUserId = null;

    public string $nama = '';

    public string $unit = '';

    public string $kontak = '';

    public string $keperluan = '';

    public string $kembaliTanggal = '';

    public string $kembaliJam = '16:00';

    public ?string $pesan = null;

    public ?string $nomorBerhasil = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('peminjaman.catat'), 403);

        $this->kembaliTanggal = now()->addDay()->toDateString();
    }

    public function tambah(string $asetId): void
    {
        $aset = $this->aksesAset()->whereKey($asetId)->first();

        if ($aset === null) {
            $this->pesan = 'Aset tidak ditemukan atau bukan di ruangan yang Anda kelola.';

            return;
        }

        if (! in_array($aset->getKey(), $this->daftar, true)) {
            $this->daftar[] = $aset->getKey();
        }

        $this->pesan = null;
        $this->cari = '';
    }

    public function tambahDariTeks(ResolusiLabel $resolusi): void
    {
        $aset = $resolusi->handle($this->teksPindai);
        $this->teksPindai = '';

        if ($aset === null) {
            $this->pesan = 'Kode tidak ditemukan.';

            return;
        }

        $this->tambah($aset->getKey());
    }

    public function hapus(string $asetId): void
    {
        $this->daftar = array_values(array_diff($this->daftar, [$asetId]));
    }

    public function pilihPeminjam(string $userId): void
    {
        $user = User::query()->where('aktif', true)->find($userId);
        $this->peminjamUserId = $user?->getKey();
        $this->cariPeminjam = $user ? $user->name : '';
    }

    public function catat(): void
    {
        $this->validate([
            'keperluan' => ['required', 'string', 'max:1000'],
            'kembaliTanggal' => ['required', 'date', 'after_or_equal:today'],
            'kembaliJam' => ['required', 'date_format:H:i'],
            'nama' => [$this->modePeminjam === 'manual' ? 'required' : 'nullable', 'string', 'max:150'],
            'peminjamUserId' => [$this->modePeminjam === 'akun' ? 'required' : 'nullable', 'uuid'],
        ]);

        /** @var User $pelaku */
        $pelaku = auth()->user();
        $kembali = Carbon::parse("{$this->kembaliTanggal} {$this->kembaliJam}");

        $peminjam = $this->modePeminjam === 'akun'
            ? ['peminjam_user_id' => $this->peminjamUserId, 'unit_peminjam' => $this->unit ?: null, 'kontak_peminjam' => $this->kontak ?: null]
            : ['nama_peminjam' => $this->nama, 'unit_peminjam' => $this->unit ?: null, 'kontak_peminjam' => $this->kontak ?: null];

        /** @var Peminjaman $peminjaman */
        $peminjaman = app(CatatPeminjamanLangsung::class)->handle($this->daftar, $peminjam, $this->keperluan, $kembali, $pelaku);

        $this->nomorBerhasil = $peminjaman->nomor;
        $this->reset('daftar', 'cari', 'teksPindai', 'peminjamUserId', 'cariPeminjam', 'nama', 'unit', 'kontak', 'keperluan', 'pesan');
    }

    /** Batas maksimal hari pinjam untuk petunjuk di formulir. */
    public function maksHari(): int
    {
        return (int) Pengaturan::ambil('maks_hari_pinjam', 14);
    }

    /** @return Collection<int, Aset> */
    #[Computed]
    public function isiKeranjang(): Collection
    {
        return $this->aksesAset()->with('ruangan')->whereIn('id', $this->daftar)->get();
    }

    /** @return Collection<int, Aset> */
    #[Computed]
    public function hasilCari(): Collection
    {
        $kata = trim($this->cari);

        if (mb_strlen($kata) < 2) {
            return collect();
        }

        return $this->aksesAset()->with('ruangan')
            ->where('status', 'aktif')->where('kondisi', '!=', 'RB')->where('dapat_dipinjam', true)
            ->whereNotIn('id', $this->daftar)
            ->where(fn ($q) => $q->where('nama', 'like', "%{$kata}%")->orWhere('merk_tipe', 'like', "%{$kata}%")
                ->orWhere('kode_barang', 'like', "%{$kata}%")->orWhere('kode_internal', 'like', "%{$kata}%")
                ->orWhere('nup', is_numeric($kata) ? (int) $kata : -1))
            ->orderBy('nama')->limit(10)->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function hasilPeminjam(): Collection
    {
        $kata = trim($this->cariPeminjam);

        if (mb_strlen($kata) < 2 || $this->peminjamUserId !== null) {
            return collect();
        }

        return User::query()->where('aktif', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'civitas'))
            ->where(fn ($q) => $q->where('name', 'like', "%{$kata}%")->orWhere('email', 'like', "%{$kata}%"))
            ->orderBy('name')->limit(8)->get();
    }

    /** Alasan aset di keranjang tidak tersedia pada rentang yang dipilih (untuk peringatan dini). */
    public function alasanTidakTersedia(Aset $aset): ?string
    {
        try {
            return app(CekKetersediaan::class)->alasan($aset, now(), Carbon::parse("{$this->kembaliTanggal} {$this->kembaliJam}"));
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return Builder<Aset> */
    private function aksesAset(): Builder
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return Aset::query()->dikelolaOleh($pengguna);
    }

    public function render(): View
    {
        return view('livewire.keranjang');
    }
}
