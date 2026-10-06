<?php

namespace Database\Factories;

use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusBmn;
use App\Enums\SumberPerolehan;
use App\Models\Aset;
use App\Models\Ruangan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Aset> */
class AsetFactory extends Factory
{
    protected $model = Aset::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $nup = 0;
        $nup++;

        return [
            'status_bmn' => StatusBmn::Tercatat,
            'kode_barang' => '3100102001',
            'nup' => $nup,
            'nama' => fake()->randomElement(['Meja Kerja', 'Kursi Dosen', 'Proyektor', 'Laptop', 'Lemari Arsip']),
            'merk_tipe' => fake()->randomElement(['Olympic', 'Epson EB-X06', 'Lenovo ThinkPad', 'Informa']),
            'tahun_perolehan' => fake()->numberBetween(2015, 2025),
            'tanggal_perolehan' => fake()->date(),
            'nilai_perolehan' => fake()->randomFloat(2, 100000, 25000000),
            'sumber_perolehan' => SumberPerolehan::Pembelian,
            'ruangan_id' => Ruangan::query()->create(['kode' => 'R-'.fake()->unique()->numerify('####'), 'nama' => 'Ruang Uji'])->id,
            'kondisi' => KondisiAset::Baik,
            'status' => StatusAset::Aktif,
            'dapat_dipinjam' => false,
        ];
    }

    public function diRuangan(Ruangan $ruangan): static
    {
        return $this->state(fn () => ['ruangan_id' => $ruangan->id]);
    }

    public function belumTercatat(string $kodeInternal): static
    {
        return $this->state(fn () => ['status_bmn' => StatusBmn::BelumTercatat, 'kode_barang' => null, 'nup' => null, 'kode_internal' => $kodeInternal]);
    }

    public function dapatDipinjam(): static
    {
        return $this->state(fn () => ['dapat_dipinjam' => true]);
    }
}
