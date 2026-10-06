<?php

namespace App\Console\Commands;

use App\Actions\Aset\DaftarkanAsetMassal;
use App\Models\Aset;
use App\Models\Gedung;
use App\Models\KategoriRuangan;
use App\Models\KodefikasiBarang;
use App\Models\LabelLama;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\User;
use Database\Seeders\KategoriRuanganSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Menyiapkan data UAT di staging (05-UJI §1): akun tiap peran (dua PIC: Lab Komputer & Aula; dua civitas), dua ruangan
 * berikut PIC-nya, dan barang contoh (20 kursi, 10 komputer, 5 proyektor dapat dipinjam, 3 Rusak Berat) lengkap dengan
 * label lama. Idempoten — dijalankan ulang tidak menggandakan data. TIDAK untuk produksi sungguhan: pada APP_ENV=production
 * wajib `--staging` agar tidak terjalankan tanpa sengaja.
 */
class SiapkanUat extends Command
{
    protected $signature = 'siman:siapkan-uat {--kata-sandi= : Kata sandi akun uji (bawaan: acak, dicetak sekali)} {--staging : Izinkan pada APP_ENV=production (staging memakai konfigurasi produksi)}';

    protected $description = 'Siapkan akun dan data contoh untuk UAT di staging';

    /** peran => [surel, nama] */
    private const AKUN = [
        ['super-admin', 'uat.super@unsil.ac.id', 'UAT Super Admin'],
        ['admin-bmn', 'uat.admin@unsil.ac.id', 'UAT Admin BMN'],
        ['pejabat-penatausahaan', 'uat.pejabat@unsil.ac.id', 'UAT Pejabat Penatausahaan'],
        ['pic-ruangan', 'uat.pic.lab@unsil.ac.id', 'UAT PIC Lab Komputer'],
        ['pic-ruangan', 'uat.pic.aula@unsil.ac.id', 'UAT PIC Aula'],
        ['pimpinan', 'uat.pimpinan@unsil.ac.id', 'UAT Pimpinan'],
        ['civitas', 'uat.dosen@unsil.ac.id', 'UAT Dosen'],
        ['civitas', 'uat.ormawa@unsil.ac.id', 'UAT Pengurus Ormawa'],
    ];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('staging')) {
            $this->error('APP_ENV=production: tambahkan --staging bila ini memang staging. Perintah ini tidak boleh dijalankan di produksi sungguhan.');

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => PeranDanIzinSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => KategoriRuanganSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => PengaturanSeeder::class, '--force' => true]);

        $kataSandi = (string) ($this->option('kata-sandi') ?: Str::password(16, symbols: false));
        $akun = $this->buatAkun($kataSandi);
        [$lab, $aula] = $this->buatRuangan($akun);
        $this->buatAset($lab, $aula, $akun['uat.admin@unsil.ac.id']);

        $this->info('Data UAT siap.');
        $this->table(['Peran', 'Surel'], collect(self::AKUN)->map(fn (array $a): array => [$a[0], $a[1]])->all());
        $this->line("Kata sandi semua akun uji: {$kataSandi}");
        $this->warn('Akun super-admin/admin-bmn akan diminta mengatur MFA pada login pertama.');

        return self::SUCCESS;
    }

    /** @return array<string, User> */
    private function buatAkun(string $kataSandi): array
    {
        $hasil = [];

        foreach (self::AKUN as [$peran, $surel, $nama]) {
            $user = User::query()->firstOrNew(['email' => $surel]);
            $user->forceFill(['name' => $nama, 'aktif' => true]);

            if (! $user->exists) {
                $user->password = $kataSandi;
            }

            $user->save();
            $user->syncRoles([$peran]);
            $hasil[$surel] = $user;
        }

        return $hasil;
    }

    /**
     * @param  array<string, User>  $akun
     * @return array{0: Ruangan, 1: Ruangan}
     */
    private function buatRuangan(array $akun): array
    {
        $gedung = Gedung::query()->firstOrCreate(['kode' => 'UAT'], ['nama' => 'Gedung UAT', 'alamat' => 'FKIP UNSIL']);
        $kategoriLab = KategoriRuangan::query()->firstOrCreate(['nama' => 'Laboratorium'], ['adalah_laboratorium' => true, 'adalah_ruang_kelas' => false]);
        $kategoriAula = KategoriRuangan::query()->firstOrCreate(['nama' => 'Aula'], ['adalah_laboratorium' => false, 'adalah_ruang_kelas' => false]);
        $prodi = Prodi::query()->firstOrCreate(['kode' => 'UAT-PTI'], ['nama' => 'Pendidikan Teknologi Informasi (UAT)']);

        $lab = Ruangan::query()->firstOrCreate(['kode' => 'UAT-LAB'], [
            'nama' => 'Lab Komputer', 'gedung_id' => $gedung->id, 'kategori_ruangan_id' => $kategoriLab->id,
            'lantai' => 2, 'kapasitas' => 40, 'luas_m2' => 72, 'dapat_dipinjam' => true,
        ]);
        $aula = Ruangan::query()->firstOrCreate(['kode' => 'UAT-AULA'], [
            'nama' => 'Aula', 'gedung_id' => $gedung->id, 'kategori_ruangan_id' => $kategoriAula->id,
            'lantai' => 1, 'kapasitas' => 200, 'luas_m2' => 250, 'dapat_dipinjam' => true,
        ]);
        $lab->prodi()->syncWithoutDetaching([$prodi->id]);

        $akun['uat.pic.lab@unsil.ac.id']->ruanganDikelola()->syncWithoutDetaching([$lab->id]);
        $akun['uat.pic.aula@unsil.ac.id']->ruanganDikelola()->syncWithoutDetaching([$aula->id]);

        return [$lab, $aula];
    }

    private function buatAset(Ruangan $lab, Ruangan $aula, User $admin): void
    {
        if (Aset::query()->where('ruangan_id', $lab->id)->exists() || Aset::query()->where('ruangan_id', $aula->id)->exists()) {
            $this->line('Barang contoh sudah ada; dilewati.');

            return;
        }

        foreach ([['3100102001', 'Kursi', 'Mebeler'], ['3100203001', 'Personal Computer', 'Elektronik'], ['3100301001', 'Proyektor', 'Elektronik']] as [$kode, $uraian, $kategori]) {
            KodefikasiBarang::query()->firstOrCreate(['kode' => $kode], ['uraian' => $uraian, 'tingkat' => 5, 'kategori_lokal' => $kategori]);
        }

        $dasar = ['status_bmn' => 'tercatat', 'tahun_perolehan' => 2024, 'tanggal_perolehan' => '2024-03-01', 'sumber_perolehan' => 'pembelian', 'kondisi' => 'B'];
        $massal = app(DaftarkanAsetMassal::class);

        $kursi = $massal->handle([...$dasar, 'kode_barang' => '3100102001', 'nama' => 'Kursi Kuliah', 'merk_tipe' => 'Chitose', 'nilai_perolehan' => '650000', 'ruangan_id' => $aula->id], 20, 1, $admin);
        $komputer = $massal->handle([...$dasar, 'kode_barang' => '3100203001', 'nama' => 'Komputer Desktop', 'merk_tipe' => 'Lenovo ThinkCentre', 'nilai_perolehan' => '9500000', 'ruangan_id' => $lab->id], 10, 1, $admin);
        $proyektor = $massal->handle([...$dasar, 'kode_barang' => '3100301001', 'nama' => 'Proyektor', 'merk_tipe' => 'Epson EB-X500', 'nilai_perolehan' => '7500000', 'ruangan_id' => $aula->id, 'dapat_dipinjam' => true], 5, 1, $admin);

        // Tiga barang Rusak Berat (bahan skenario usulan penghapusan)
        foreach ($kursi->take(3) as $a) {
            $a->forceFill(['kondisi' => 'RB'])->saveQuietly();
        }

        // Label lama (format SIMAN-2) untuk skenario pindai P-01
        foreach ($komputer->take(10)->values() as $i => $a) {
            LabelLama::query()->firstOrCreate(['teks' => 'KOM-'.(935000 + $i).'-1'], ['aset_id' => $a->id]);
        }

        $this->line(sprintf('Barang contoh: %d kursi, %d komputer, %d proyektor.', $kursi->count(), $komputer->count(), $proyektor->count()));
    }
}
