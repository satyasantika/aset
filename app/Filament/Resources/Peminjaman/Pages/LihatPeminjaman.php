<?php

namespace App\Filament\Resources\Peminjaman\Pages;

use App\Actions\Peminjaman\BatalkanPeminjaman;
use App\Actions\Peminjaman\KembalikanPeminjaman;
use App\Actions\Peminjaman\SerahkanPeminjaman;
use App\Actions\Peminjaman\SetujuiPeminjaman;
use App\Actions\Peminjaman\TolakPeminjaman;
use App\Enums\JenisPeminjam;
use App\Enums\KondisiAset;
use App\Enums\StatusPeminjaman;
use App\Filament\Resources\Peminjaman\PeminjamanResource;
use App\Models\Peminjaman;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class LihatPeminjaman extends ViewRecord
{
    protected static string $resource = PeminjamanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setujui')->label('Setujui')->color('success')->icon('heroicon-o-check')
                ->visible(fn (): bool => $this->bisa('putuskan', StatusPeminjaman::Diajukan))
                ->requiresConfirmation()
                ->schema([Textarea::make('catatan')->label('Catatan (opsional)')->maxLength(1000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(SetujuiPeminjaman::class)->handle($this->peminjaman(), auth()->user(), $data['catatan'] ?? null), 'Peminjaman disetujui')),
            Action::make('tolak')->label('Tolak')->color('danger')->icon('heroicon-o-x-mark')
                ->visible(fn (): bool => $this->bisa('putuskan', StatusPeminjaman::Diajukan))
                ->schema([Textarea::make('catatan')->label('Alasan penolakan')->required()->maxLength(1000)])
                ->action(fn (array $data) => $this->jalankan(fn () => app(TolakPeminjaman::class)->handle($this->peminjaman(), auth()->user(), $data['catatan']), 'Peminjaman ditolak')),
            Action::make('serahkan')->label('Serahkan barang')->color('info')->icon('heroicon-o-hand-raised')
                ->visible(fn (): bool => $this->peminjaman()->jenis_peminjam === JenisPeminjam::Civitas && $this->bisa('putuskan', StatusPeminjaman::Disetujui))
                ->requiresConfirmation()
                ->action(fn () => $this->jalankan(fn () => app(SerahkanPeminjaman::class)->handle($this->peminjaman(), auth()->user()), 'Serah terima dicatat')),
            Action::make('kembalikan')->label('Terima pengembalian')->color('primary')->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (): bool => $this->bisa('putuskan', StatusPeminjaman::Dipinjam))
                ->modalDescription('Tentukan kondisi SETIAP barang saat kembali. Pengembalian sebagian tidak diterima.')
                ->fillForm(fn (): array => ['barang' => $this->barangUntukForm()])
                ->schema([
                    Repeater::make('barang')->label('Barang')->addable(false)->deletable(false)->reorderable(false)->schema([
                        Hidden::make('aset_id'),
                        TextInput::make('nama')->label('Barang')->disabled()->dehydrated(false),
                        Select::make('kondisi')->label('Kondisi saat kembali')->options(KondisiAset::opsi())->required(),
                        TextInput::make('catatan')->label('Catatan')->maxLength(500),
                    ])->columns(3),
                ])
                ->action(function (array $data): void {
                    /** @var list<array{aset_id: string, kondisi: string, catatan: string|null}> $baris */
                    $baris = array_values($data['barang']);
                    $kondisi = array_column($baris, 'kondisi', 'aset_id');
                    $catatan = array_column($baris, 'catatan', 'aset_id');
                    $this->jalankan(fn () => app(KembalikanPeminjaman::class)->handle($this->peminjaman(), $kondisi, auth()->user(), $catatan), 'Pengembalian dicatat');
                }),
            Action::make('batalkan')->label('Batalkan')->color('gray')
                ->visible(fn (): bool => in_array($this->peminjaman()->status, [StatusPeminjaman::Diajukan, StatusPeminjaman::Disetujui], true)
                    && (auth()->user()?->can('batalkan', $this->peminjaman()) ?? false))
                ->requiresConfirmation()
                ->action(fn () => $this->jalankan(fn () => app(BatalkanPeminjaman::class)->handle($this->peminjaman(), auth()->user()), 'Peminjaman dibatalkan')),
        ];
    }

    private function peminjaman(): Peminjaman
    {
        /** @var Peminjaman $record */
        $record = $this->getRecord();

        return $record;
    }

    private function bisa(string $kemampuan, StatusPeminjaman $status): bool
    {
        return $this->peminjaman()->status === $status && (auth()->user()?->can($kemampuan, $this->peminjaman()) ?? false);
    }

    private function jalankan(callable $aksi, string $pesan): void
    {
        $aksi();
        Notification::make()->success()->title($pesan)->send();
        $this->refreshFormData(['status', 'diputuskan_pada', 'catatan_keputusan', 'diserahkan_pada', 'dikembalikan_pada']);
    }

    /** @return list<array{aset_id: string, nama: string, kondisi: string, catatan: null}> */
    private function barangUntukForm(): array
    {
        $hasil = [];

        foreach ($this->peminjaman()->item()->with('aset')->get() as $item) {
            $hasil[] = [
                'aset_id' => $item->aset_id,
                'nama' => "{$item->aset->nama} — {$item->aset->kode_tampil}",
                'kondisi' => $item->kondisi_saat_pinjam->value,
                'catatan' => null,
            ];
        }

        return $hasil;
    }
}
