<?php

namespace App\Livewire;

use App\Actions\Inventarisasi\CatatHasilPindai;
use App\Actions\Inventarisasi\CatatTemuanBerlebih;
use App\Actions\Inventarisasi\SelesaikanInventarisasiRuangan;
use App\Actions\Label\ResolusiLabel;
use App\Enums\HasilInventarisasi as Hasil;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusInventarisasiRuangan;
use App\Models\Aset;
use App\Models\HasilInventarisasi;
use App\Models\InventarisasiRuangan;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pemindaian inventarisasi di ponsel (BR-14): aset ruangan, pindai QR (URL baru/label lama) atau ketuk, koreksi kondisi,
 * temuan berlebih, progres, dan selesai ruangan (sisa otomatis tidak_ditemukan).
 *
 * @property-read Collection<int, Aset> $daftarAset
 * @property-read Collection<string, HasilInventarisasi> $hasilPerAset
 * @property-read int $progres
 */
#[Layout('components.layouts.lapangan')]
#[Title('Inventarisasi ruangan')]
class InventarisasiRuanganHalaman extends Component
{
    #[Locked]
    public string $inventarisasiId = '';

    public string $teks = '';

    public ?string $pesan = null;

    public ?string $peringatan = null;

    public ?string $terakhirId = null;

    public string $deskripsiTemuan = '';

    public string $fotoTemuan = '';

    public bool $konfirmasiSelesai = false;

    public function mount(string $periode, string $ruangan): void
    {
        $inv = InventarisasiRuangan::query()->with(['periode', 'ruangan'])->where('periode_id', $periode)->where('ruangan_id', $ruangan)->firstOrFail();

        abort_unless(auth()->user()?->can('pindai', $inv), 403);

        $this->inventarisasiId = $inv->getKey();
    }

    public function pindai(ResolusiLabel $resolusi): void
    {
        $this->reset('pesan', 'peringatan');
        $aset = $resolusi->handle($this->teks);
        $this->teks = '';

        if ($aset === null) {
            $this->peringatan = 'Kode tidak ditemukan di data aset.';

            return;
        }

        $this->tandaiDitemukan($aset->getKey());
    }

    /** Ketuk pada daftar aset (tanpa memindai). */
    public function tandaiDitemukan(string $asetId, ?string $kondisi = null): void
    {
        $this->reset('pesan', 'peringatan');
        $aset = Aset::query()->with('ruangan')->find($asetId);

        if ($aset === null) {
            $this->peringatan = 'Aset tidak ditemukan.';

            return;
        }

        try {
            app(CatatHasilPindai::class)->handle($this->inventarisasi(), $aset, $this->pengguna(), $kondisi ? KondisiAset::from($kondisi) : null);
        } catch (ValidationException $e) {
            $this->peringatan = collect($e->errors())->flatten()->first();

            return;
        }

        $this->terakhirId = $aset->getKey();
        $this->pesan = "{$aset->nama} ditandai ditemukan.";
        $this->segarkan();
    }

    /** Koreksi kondisi aset yang baru saja dipindai → `kondisi_berubah` bila berbeda dari data. */
    public function koreksiKondisi(string $asetId, string $kondisi): void
    {
        $this->tandaiDitemukan($asetId, $kondisi);
    }

    public function catatTemuanBerlebih(): void
    {
        $this->reset('pesan', 'peringatan');
        $this->validate(['deskripsiTemuan' => ['required', 'string', 'min:3', 'max:1000'], 'fotoTemuan' => ['nullable', 'string', 'max:2048', new TautanBerkasValid]]);

        app(CatatTemuanBerlebih::class)->handle($this->inventarisasi(), $this->deskripsiTemuan, $this->fotoTemuan ?: null, $this->pengguna());

        $this->pesan = 'Temuan berlebih dicatat.';
        $this->reset('deskripsiTemuan', 'fotoTemuan');
        $this->segarkan();
    }

    public function selesai(): void
    {
        $this->reset('pesan', 'peringatan');
        app(SelesaikanInventarisasiRuangan::class)->handle($this->inventarisasi(), $this->pengguna());

        $this->konfirmasiSelesai = false;
        $this->pesan = 'Inventarisasi ruangan selesai. Barang yang belum dipindai dicatat tidak ditemukan.';
        $this->segarkan();
    }

    public function inventarisasi(): InventarisasiRuangan
    {
        return InventarisasiRuangan::query()->with(['periode', 'ruangan'])->findOrFail($this->inventarisasiId);
    }

    public function sudahSelesai(): bool
    {
        return $this->inventarisasi()->status === StatusInventarisasiRuangan::Selesai;
    }

    /** @return Collection<int, Aset> */
    #[Computed]
    public function daftarAset(): Collection
    {
        $inv = $this->inventarisasi();

        return Aset::query()->where('ruangan_id', $inv->ruangan_id)
            ->whereNotIn('status', [StatusAset::Hilang->value, StatusAset::Dihapus->value])->orderBy('nama')->orderBy('kode_barang')->orderBy('nup')->get();
    }

    /** @return Collection<string, HasilInventarisasi> */
    #[Computed]
    public function hasilPerAset(): Collection
    {
        return HasilInventarisasi::query()->where('inventarisasi_ruangan_id', $this->inventarisasiId)->whereNotNull('aset_id')->get()->keyBy('aset_id');
    }

    /** @return Collection<int, HasilInventarisasi> */
    #[Computed]
    public function temuanBerlebih(): Collection
    {
        return HasilInventarisasi::query()->where('inventarisasi_ruangan_id', $this->inventarisasiId)->where('hasil', Hasil::Berlebih->value)->latest()->get();
    }

    #[Computed]
    public function progres(): int
    {
        return SelesaikanInventarisasiRuangan::progres($this->inventarisasi());
    }

    private function segarkan(): void
    {
        unset($this->hasilPerAset, $this->progres, $this->temuanBerlebih, $this->daftarAset);
    }

    private function pengguna(): User
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();

        return $pengguna;
    }

    public function render(): View
    {
        return view('livewire.inventarisasi-ruangan', ['inv' => $this->inventarisasi()]);
    }
}
