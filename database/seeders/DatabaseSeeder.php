<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(PeranDanIzinSeeder::class);

        // Akun super-admin contoh hanya untuk lingkungan lokal; produksi dibuat manual (tanpa kata sandi bawaan).
        if (app()->environment('local')) {
            User::query()->firstOrCreate(
                ['email' => 'admin@unsil.ac.id'],
                ['name' => 'Admin Lokal', 'password' => 'password', 'aktif' => true],
            )->assignRole('super-admin');
        }
    }
}
