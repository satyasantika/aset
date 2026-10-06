<?php

namespace App\Actions\Api;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Membuat token API untuk satu klien eksternal (mis. Surat): akun klien tanpa peran dan tidak dapat masuk panel
 * (`aktif = false`), plus token Sanctum dengan ability terbatas. Teks token hanya dikembalikan sekali.
 */
class BuatTokenKlien
{
    public const ABILITY = ['ruangan:baca', 'ruangan:pakai'];

    /** @param  list<string>  $abilities */
    public function handle(string $nama, array $abilities, User $pelaku): string
    {
        Gate::forUser($pelaku)->authorize('pengaturan.kelola');

        $nama = trim($nama);
        $abilities = array_values(array_unique($abilities));

        if ($nama === '') {
            throw ValidationException::withMessages(['nama' => 'Nama klien wajib diisi.']);
        }

        if ($abilities === [] || array_diff($abilities, self::ABILITY) !== []) {
            throw ValidationException::withMessages(['abilities' => 'Pilih ability yang valid: '.implode(', ', self::ABILITY).'.']);
        }

        return DB::transaction(function () use ($nama, $abilities): string {
            $klien = User::query()->create([
                'name' => $nama,
                'email' => 'klien-'.Str::slug($nama).'-'.Str::lower(Str::random(6)).'@klien.siman.invalid',
                'password' => Hash::make(Str::random(48)),
                'aktif' => false,
            ]);

            return $klien->createToken($nama, $abilities)->plainTextToken;
        });
    }
}
