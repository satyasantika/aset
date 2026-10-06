<?php

namespace Database\Factories;

use App\Enums\JenisPeminjam;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Peminjaman> */
class PeminjamanFactory extends Factory
{
    protected $model = Peminjaman::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $urut = 0;
        $urut++;

        return [
            'nomor' => 'PJM-'.now()->year.'-'.str_pad((string) (90000 + $urut), 5, '0', STR_PAD_LEFT),
            'jenis_peminjam' => JenisPeminjam::Civitas,
            'nama_peminjam' => fake()->name(),
            'kontak_peminjam' => fake()->numerify('08##########'),
            'unit_peminjam' => 'Prodi Contoh',
            'keperluan' => 'Kegiatan kuliah',
            'mulai' => now()->addDay()->setTime(8, 0),
            'rencana_kembali' => now()->addDay()->setTime(16, 0),
            'status' => StatusPeminjaman::Disetujui,
            'dicatat_oleh' => User::factory(),
        ];
    }

    public function status(StatusPeminjaman $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function rentang(string $mulai, string $selesai): static
    {
        return $this->state(fn () => ['mulai' => $mulai, 'rencana_kembali' => $selesai]);
    }

    /** Menambahkan item untuk aset yang diberikan (kondisi saat pinjam = kondisi aset saat ini). */
    public function untuk(Aset ...$aset): static
    {
        return $this->afterCreating(function (Peminjaman $p) use ($aset) {
            foreach ($aset as $a) {
                $p->item()->create(['aset_id' => $a->id, 'kondisi_saat_pinjam' => $a->kondisi]);
            }
        });
    }
}
