<?php

use App\Enums\StatusPeminjaman;
use App\Filament\Pages\Laporan;
use App\Models\Aset;
use App\Models\KategoriRuangan;
use App\Models\Peminjaman;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['queue.default' => 'sync']);
});

function lpUser(string $peran, array $ruangan = []): User
{
    $u = User::factory()->create();
    $u->assignRole($peran);
    $u->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));
    $u->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $u;
}

/** Jalankan satu aksi ekspor sebagai pengguna dan kembalikan baris CSV (tanpa header) beserta header. */
function lpEkspor(User $pengguna, string $aksi): array
{
    test()->actingAs($pengguna);
    Livewire::test(Laporan::class)
        ->callAction($aksi, data: [])
        ->assertHasNoActionErrors();

    $export = Export::query()->orderByDesc('id')->firstOrFail();
    expect($export->file_disk)->toBe('tmp');

    $dir = $export->getFileDirectory().DIRECTORY_SEPARATOR;
    $baca = fn (string $berkas): array => array_map('str_getcsv', array_values(array_filter(explode("\n", trim(Storage::disk('tmp')->get($dir.$berkas))))));
    $header = array_map(fn (string $h): string => trim($h, "\xEF\xBB\xBF\""), $baca('headers.csv')[0]);
    $baris = $baca('0000000000000001.csv');

    return [$header, $baris];
}

/** @return array{r1: Ruangan, r2: Ruangan} */
function lpData(): array
{
    $lab = KategoriRuangan::query()->create(['nama' => 'Laboratorium', 'adalah_laboratorium' => true, 'adalah_ruang_kelas' => false]);
    $gudang = KategoriRuangan::query()->create(['nama' => 'Gudang', 'adalah_laboratorium' => false, 'adalah_ruang_kelas' => false]);
    $prodi = Prodi::query()->create(['kode' => 'PTI', 'nama' => 'Pendidikan Teknologi Informasi']);
    $r1 = Ruangan::query()->create(['kode' => 'R-1', 'nama' => 'Lab Satu', 'kategori_ruangan_id' => $lab->id, 'luas_m2' => 60]);
    $r2 = Ruangan::query()->create(['kode' => 'R-2', 'nama' => 'Gudang Dua', 'kategori_ruangan_id' => $gudang->id]);
    $r1->prodi()->attach($prodi->id);

    Aset::factory()->diRuangan($r1)->create(['nama' => 'Komputer Lab', 'kode_barang' => '3100203001', 'nup' => 1]);
    Aset::factory()->diRuangan($r1)->create(['nama' => 'Meja Lab', 'kode_barang' => '3100102001', 'nup' => 2]);
    Aset::factory()->diRuangan($r2)->create(['nama' => 'Lemari Gudang', 'kode_barang' => '3100102001', 'nup' => 3]);
    Aset::factory()->diRuangan($r2)->create(['nama' => 'Kursi Rusak', 'kode_barang' => '3100102001', 'nup' => 4, 'kondisi' => 'RB']);

    return ['r1' => $r1, 'r2' => $r2];
}

test('rekonsiliasi memuat kode, NUP, lokasi, kondisi, dan nilai; PIC hanya ruangannya', function () {
    $d = lpData();

    [$header, $baris] = lpEkspor(lpUser('admin-bmn'), 'eksporRekonsiliasi');
    expect($header)->toContain('Kode Barang', 'NUP', 'Kondisi', 'Nilai Perolehan (Rp)')
        ->and($baris)->toHaveCount(4);

    [, $barisPic] = lpEkspor(lpUser('pic-ruangan', [$d['r1']]), 'eksporRekonsiliasi');
    expect($barisPic)->toHaveCount(2);
});

test('daftar RB dan hilang hanya memuat aset rusak berat atau hilang', function () {
    lpData();
    Aset::factory()->create(['nama' => 'Proyektor Hilang', 'status' => 'hilang', 'kode_barang' => '3100102001', 'nup' => 9]);

    [, $baris] = lpEkspor(lpUser('pimpinan'), 'eksporRbHilang');

    expect(collect($baris)->pluck(0)->sort()->values()->all())->toBe(['Barang Hilang', 'Barang Rusak Berat']);
});

test('riwayat peminjaman menyamarkan data pribadi bagi pimpinan', function () {
    lpData();
    $aset = Aset::query()->where('nama', 'Meja Lab')->firstOrFail();
    Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->untuk($aset)->create(['nama_peminjam' => 'Budi Rahasia', 'kontak_peminjam' => '0812999', 'unit_peminjam' => 'Prodi X']);

    [, $admin] = lpEkspor(lpUser('admin-bmn'), 'eksporPeminjaman');
    expect(implode('|', $admin[0]))->toContain('Budi Rahasia')->toContain('0812999');

    [, $pimpinan] = lpEkspor(lpUser('pimpinan'), 'eksporPeminjaman');
    expect(implode('|', $pimpinan[0]))->not->toContain('Budi Rahasia')->not->toContain('0812999')->toContain('(dirahasiakan)');
});

test('DKPS: sarana hanya di lab/kelas, TIK berdasar kode, prasarana per ruangan dengan prodi', function () {
    lpData();

    [, $sarana] = lpEkspor(lpUser('pimpinan'), 'eksporDkpsSarana');
    expect($sarana)->toHaveCount(2)->and(implode('|', $sarana[0]))->toContain('Pendidikan Teknologi Informasi');

    [, $tik] = lpEkspor(lpUser('pimpinan'), 'eksporDkpsTik');
    expect($tik)->toHaveCount(1)->and($tik[0][1])->toBe('Komputer Lab');

    [$h, $prasarana] = lpEkspor(lpUser('pimpinan'), 'eksporDkpsPrasarana');
    expect($h)->toContain('Luas (m²)')->and(collect($prasarana)->pluck(1)->all())->toContain('R-1', 'R-2');
});

test('rekap pemeliharaan dan log hanya untuk yang berhak', function () {
    lpData();
    $aset = Aset::query()->firstOrFail();
    TiketPemeliharaan::query()->create(['nomor' => 'TKT-L-1', 'aset_id' => $aset->id, 'sumber' => 'publik', 'deskripsi' => 'rusak', 'nama_pelapor' => 'Pelapor Rahasia']);

    [, $tiket] = lpEkspor(lpUser('pimpinan'), 'eksporPemeliharaan');
    expect($tiket)->toHaveCount(1)->and(implode('|', $tiket[0]))->not->toContain('Pelapor Rahasia');

    test()->actingAs(lpUser('pimpinan'));
    Livewire::test(Laporan::class)->assertActionHidden('eksporLog');

    test()->actingAs(lpUser('civitas'))->get('/admin/laporan')->assertForbidden();
});
