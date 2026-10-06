<?php

namespace App\Livewire;

use App\Actions\Aset\UbahKondisiAset;
use App\Actions\Label\ResolusiLabel;
use App\Actions\Mutasi\AjukanMutasi;
use App\Actions\Pemeliharaan\TerimaLaporanKerusakan;
use App\Enums\KondisiAset;
use App\Models\Aset;
use App\Models\KodefikasiBarang;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
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

    public ?string $tujuanMutasi = null;

    public string $alasanMutasi = '';

    public ?string $nomorMutasi = null;

    public string $deskripsiKerusakan = '';

    public ?string $nomorTiket = null;

    public function cari(ResolusiLabel $resolusi): void
    {
        $this->validate(['teks' => ['required', 'string', 'max:500']]);

        $aset = $resolusi->handle($this->teks);

        $this->asetId = $aset?->getKey();
        $this->pesan = $aset === null ? 'Data untuk kode "'.mb_strimwidth($this->teks, 0, 80, '…').'" tidak ditemukan.' : null;
        $this->kondisiBaru = null;
        $this->nomorMutasi = null;
        $this->nomorTiket = null;
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

    public function ajukanMutasi(): void
    {
        $aset = $this->aset;
        abort_if($aset === null || $aset->ruangan === null, 404);

        $this->validate([
            'tujuanMutasi' => ['required', 'uuid', 'exists:ruangan,id'],
            'alasanMutasi' => ['required', 'string', 'max:1000'],
        ]);

        /** @var User $pelaku */
        $pelaku = auth()->user();

        $mutasi = app(AjukanMutasi::class)->handle(
            $aset->ruangan, Ruangan::query()->findOrFail($this->tujuanMutasi), [$aset->getKey()], $this->alasanMutasi, $pelaku,
        );

        $this->nomorMutasi = $mutasi->nomor;
        $this->reset('tujuanMutasi', 'alasanMutasi');
    }

    /** @return Collection<string, string> */
    #[Computed]
    public function ruanganTujuan(): Collection
    {
        return Ruangan::query()->where('id', '!=', $this->aset->ruangan_id ?? '')->orderBy('nama')->pluck('nama', 'id');
    }

    /** Pengguna login (civitas/staf) melaporkan kerusakan langsung dari hasil pindai. */
    public function laporKerusakan(): void
    {
        $aset = $this->aset;
        abort_if($aset === null, 404);

        /** @var User $pelapor */
        $pelapor = auth()->user();
        $kunci = 'lapor-pindai:'.$pelapor->getKey();

        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->addError('deskripsiKerusakan', 'Terlalu banyak laporan. Coba lagi nanti.');

            return;
        }

        $this->validate(['deskripsiKerusakan' => ['required', 'string', 'min:5', 'max:'.TerimaLaporanKerusakan::MAKS_DESKRIPSI]]);
        RateLimiter::hit($kunci, 3600);

        $tiket = app(TerimaLaporanKerusakan::class)->handle($aset, $this->deskripsiKerusakan, null, null, null, $pelapor);

        $this->nomorTiket = $tiket->nomor;
        $this->reset('deskripsiKerusakan');
    }

    public function ulang(): void
    {
        $this->reset('teks', 'asetId', 'pesan', 'kondisiBaru', 'tujuanMutasi', 'alasanMutasi', 'nomorMutasi', 'deskripsiKerusakan', 'nomorTiket');
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
