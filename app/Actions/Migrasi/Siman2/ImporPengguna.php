<?php

namespace App\Actions\Migrasi\Siman2;

use App\Models\Ruangan;
use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sheet `users` → `users` + peran; lalu PIC ruangan dari `users.ruangan` (nama ruangan, JSON) dan `ruangan.userIdPIC`.
 * Kata sandi TIDAK dimigrasikan (skema hash berbeda): akun dibuat dengan kata sandi acak dan menerima surel atur kata
 * sandi. Surel dari tabel pemetaan username → @unsil.ac.id (wajib).
 */
class ImporPengguna implements Importer
{
    private const PERAN = ['admin' => 'admin-bmn', 'penanggungjawab' => 'pic-ruangan', 'pimpinan' => 'pimpinan'];

    /**
     * Surel akun yang BARU dibuat pada jalan ini (untuk surel atur kata sandi).
     *
     * @var list<string>
     */
    public array $penggunaBaru = [];

    public function nama(): string
    {
        return 'users';
    }

    public function jalankan(Konteks $konteks, BacaXlsx $xlsx): void
    {
        $this->penggunaBaru = [];

        foreach ($xlsx->baris('users') as $baris) {
            $idLama = Nilai::teks($baris['id'] ?? $baris['username'] ?? '');

            $konteks->proses('users', $idLama, fn (): string => $this->impor($konteks, $baris, $idLama));
        }

        $this->tugaskanPic($konteks, $xlsx);
    }

    /** @param  array<string, mixed>  $baris */
    private function impor(Konteks $konteks, array $baris, string $idLama): string
    {
        $username = mb_strtolower(Nilai::tidakKosong($baris['username'] ?? null) ?? throw new \InvalidArgumentException('username kosong.'));
        $surel = $konteks->pemetaanPengguna[$username] ?? null;

        if ($surel === null) {
            throw new \InvalidArgumentException("Username \"{$username}\" belum dipetakan ke surel unsil.ac.id di pemetaan-pengguna.csv.");
        }

        $surel = mb_strtolower($surel);

        if (Validator::make(['surel' => $surel], ['surel' => ['required', 'email', new SurelDomainUnsil]])->fails()) {
            throw ValidationException::withMessages(['surel' => "Surel pemetaan untuk \"{$username}\" tidak valid: {$surel}."]);
        }

        $peran = self::PERAN[mb_strtolower(Nilai::teks($baris['role'] ?? ''))] ?? null;

        if ($peran === null) {
            throw new \InvalidArgumentException('Role "'.Nilai::teks($baris['role'] ?? '').'" tidak dikenal (Admin, Penanggungjawab, Pimpinan).');
        }

        $idBaru = $konteks->idBaru('users', $idLama);
        $user = ($idBaru ? User::query()->find($idBaru) : null) ?? User::query()->where('email', $surel)->first();
        $baru = $user === null;

        $user ??= new User(['password' => Str::random(40)]);
        $user->fill([
            'name' => Nilai::tidakKosong($baris['nama'] ?? null) ?? $username,
            'email' => $surel,
            'no_hp' => Nilai::tidakKosong($baris['hp'] ?? null) ?? $user->no_hp,
            'aktif' => true,
        ])->save();

        if (! $user->hasRole($peran)) {
            $user->assignRole($peran);
        }

        if ($baru) {
            $this->penggunaBaru[] = $user->email;
        }

        return $user->getKey();
    }

    private function tugaskanPic(Konteks $konteks, BacaXlsx $xlsx): void
    {
        $ruanganPerNama = Ruangan::query()->get()->keyBy(fn (Ruangan $r) => mb_strtolower(trim($r->nama)));

        // 1) users.ruangan (daftar nama ruangan)
        foreach ($xlsx->baris('users') as $baris) {
            $idLama = Nilai::teks($baris['id'] ?? $baris['username'] ?? '');
            $user = $this->userBaru($konteks, $idLama);

            if ($user === null) {
                continue;
            }

            foreach (Nilai::daftar($baris['ruangan'] ?? null) as $nama) {
                $ruangan = $ruanganPerNama[mb_strtolower($nama)] ?? null;

                if ($ruangan === null) {
                    $konteks->peringatan('users', $idLama, "Ruangan \"{$nama}\" tidak ditemukan; penugasan PIC dilewati.");

                    continue;
                }

                $this->tugaskan($konteks, $ruangan, $user, false, $idLama);
            }
        }

        // 2) ruangan.userIdPIC (PIC utama)
        foreach ($xlsx->baris('ruangan') as $baris) {
            $pic = Nilai::tidakKosong($baris['userIdPIC'] ?? null);
            $idRuangan = Nilai::teks($baris['id'] ?? $baris['idRuangan'] ?? '');

            if ($pic === null) {
                continue;
            }

            $user = $this->userBaru($konteks, $pic);
            $ruangan = ($id = $konteks->idBaru('ruangan', $idRuangan)) ? Ruangan::query()->find($id) : null;

            if ($user === null || $ruangan === null) {
                $konteks->peringatan('ruangan', $idRuangan, "PIC (id lama {$pic}) atau ruangan belum terimpor; penugasan dilewati.");

                continue;
            }

            $this->tugaskan($konteks, $ruangan, $user, true, $idRuangan);
        }
    }

    private function userBaru(Konteks $konteks, string $idLama): ?User
    {
        $id = $konteks->idBaru('users', $idLama);

        return $id ? User::query()->find($id) : null;
    }

    private function tugaskan(Konteks $konteks, Ruangan $ruangan, User $user, bool $utama, string $idSumber): void
    {
        if (! $user->hasRole('pic-ruangan')) {
            $konteks->peringatan('users', $idSumber, "{$user->email} bukan pic-ruangan; penugasan ke {$ruangan->nama} dilewati.");

            return;
        }

        $sudahAda = $ruangan->pic()->whereKey($user->getKey())->exists();
        $sudahAda ? null : $ruangan->pic()->attach($user->getKey(), ['utama' => false]);

        if ($utama || $ruangan->pic()->wherePivot('utama', true)->doesntExist()) {
            $ruangan->tetapkanPicUtama($user);
        }
    }
}
