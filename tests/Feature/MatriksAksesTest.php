<?php

use App\Enums\StatusDbr;
use App\Enums\StatusPeminjaman;
use App\Models\Aset;
use App\Models\DbrVersi;
use App\Models\Gedung;
use App\Models\InventarisasiRuangan;
use App\Models\Mutasi;
use App\Models\Peminjaman;
use App\Models\PeriodeInventarisasi;
use App\Models\Ruangan;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

const MX_PERAN = ['super-admin', 'admin-bmn', 'pejabat-penatausahaan', 'pic-ruangan', 'pimpinan', 'civitas'];

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
});

/** Matriks hak akses PRD §3.1 — satu baris per aksi; kolom = peran yang BOLEH (super-admin selalu boleh lewat Gate::before). */
function mxFixture(): array
{
    $r = Ruangan::query()->create(['kode' => 'MX-1', 'nama' => 'Ruang MX']);
    $lain = Ruangan::query()->create(['kode' => 'MX-2', 'nama' => 'Ruang Lain']);
    $aset = Aset::factory()->diRuangan($r)->create(['kode_barang' => '3100102001', 'nup' => 1]);
    $pengguna = [];

    foreach (MX_PERAN as $peran) {
        $u = User::factory()->create(['aktif' => true]);
        $u->assignRole($peran);
        $pengguna[$peran] = $u;
    }

    $pengguna['pic-ruangan']->ruanganDikelola()->attach($r->id);
    $civitasLain = User::factory()->create();
    $civitasLain->assignRole('civitas');

    $mutasi = Mutasi::query()->create(['nomor' => 'MUT-X-1', 'ruangan_asal_id' => $r->id, 'ruangan_tujuan_id' => $lain->id, 'alasan' => 'x', 'status' => 'diajukan', 'diajukan_oleh' => $pengguna['pic-ruangan']->id]);
    $pinjam = Peminjaman::factory()->status(StatusPeminjaman::Diajukan)->untuk($aset)->create(['peminjam_user_id' => $civitasLain->id]);
    $pinjamLuar = Peminjaman::factory()->status(StatusPeminjaman::Diajukan)->untuk($aset)->create(['jenis_peminjam' => 'pihak_luar', 'peminjam_user_id' => null]);
    $tiket = TiketPemeliharaan::query()->create(['nomor' => 'TKT-X-1', 'aset_id' => $aset->id, 'sumber' => 'publik', 'deskripsi' => 'rusak', 'nama_pelapor' => 'Anon']);
    $dbr = DbrVersi::query()->create(['ruangan_id' => $r->id, 'versi' => 1, 'status' => StatusDbr::DisetujuiPic, 'snapshot' => ['aset' => []]]);
    $periode = PeriodeInventarisasi::query()->create(['nama' => 'P', 'mulai' => '2026-01-01', 'status' => 'berjalan']);
    $periodeTutup = PeriodeInventarisasi::query()->create(['nama' => 'PT', 'mulai' => '2025-01-01', 'status' => 'ditutup']);
    $invRuangan = InventarisasiRuangan::query()->create(['periode_id' => $periode->id, 'ruangan_id' => $r->id, 'petugas_id' => $pengguna['pic-ruangan']->id, 'status' => 'berjalan']);
    $usulan = UsulanPenghapusan::query()->create(['nomor' => 'USL-X-1', 'alasan' => 'x', 'status' => 'diajukan', 'pengusul_id' => $pengguna['admin-bmn']->id]);

    return compact('r', 'lain', 'aset', 'pengguna', 'mutasi', 'pinjam', 'pinjamLuar', 'tiket', 'dbr', 'periode', 'periodeTutup', 'invRuangan', 'usulan');
}

dataset('matriks', function () {
    // [label, closure(Gate-checker, fixture) => bool, peran yang boleh]
    $semua = MX_PERAN;
    $staf = ['super-admin', 'admin-bmn', 'pejabat-penatausahaan', 'pic-ruangan', 'pimpinan'];

    return [
        'master — kelola' => [fn ($g, $f) => $g('create', Gedung::class), ['super-admin', 'admin-bmn']],
        'ruangan — lihat' => [fn ($g, $f) => $g('view', $f['r']), $staf],
        'aset — lihat' => [fn ($g, $f) => $g('view', $f['aset']), $staf],
        'aset — tambah' => [fn ($g, $f) => $g('create', Aset::class), ['super-admin', 'admin-bmn']],
        'aset — ubah data induk' => [fn ($g, $f) => $g('update', $f['aset']), ['super-admin', 'admin-bmn']],
        'aset — ubah kondisi (ruangannya)' => [fn ($g, $f) => $g('ubahKondisi', $f['aset']), ['super-admin', 'admin-bmn', 'pic-ruangan']],
        'label — cetak (ruangannya)' => [fn ($g, $f) => $g('cetakLabel', $f['aset']), ['super-admin', 'admin-bmn', 'pic-ruangan']],
        'mutasi — ajukan dari ruangannya' => [fn ($g, $f) => $g('ajukan', [Mutasi::class, $f['r']]), ['super-admin', 'admin-bmn', 'pic-ruangan']],
        'mutasi — ajukan dari ruangan lain' => [fn ($g, $f) => $g('ajukan', [Mutasi::class, $f['lain']]), ['super-admin', 'admin-bmn']],
        'mutasi — setujui' => [fn ($g, $f) => $g('putuskan', $f['mutasi']), ['super-admin', 'admin-bmn']],
        'peminjaman — catat langsung (ruangannya)' => [fn ($g, $f) => $g('catat', [Peminjaman::class, $f['aset']]), ['super-admin', 'admin-bmn', 'pic-ruangan']],
        'peminjaman — ajukan online' => [fn ($g, $f) => $g('ajukan', Peminjaman::class), ['super-admin', 'civitas']],
        'peminjaman — putuskan pengajuan civitas' => [fn ($g, $f) => $g('putuskan', $f['pinjam']), ['super-admin', 'admin-bmn', 'pic-ruangan']],
        'peminjaman — putuskan pihak luar' => [fn ($g, $f) => $g('putuskan', $f['pinjamLuar']), ['super-admin', 'admin-bmn', 'pejabat-penatausahaan']],
        'pemeliharaan — kelola tiket (ruangannya)' => [fn ($g, $f) => $g('kelola', $f['tiket']), ['super-admin', 'admin-bmn', 'pic-ruangan']],
        'dbr — bangkitkan (ruangannya)' => [fn ($g, $f) => $g('bangkitkan', [DbrVersi::class, $f['r']]), ['super-admin', 'admin-bmn', 'pic-ruangan']],
        'dbr — bangkitkan ruangan lain' => [fn ($g, $f) => $g('bangkitkan', [DbrVersi::class, $f['lain']]), ['super-admin', 'admin-bmn']],
        'dbr — sahkan' => [fn ($g, $f) => $g('sahkan', $f['dbr']), ['super-admin', 'pejabat-penatausahaan']],
        'inventarisasi — kelola periode' => [fn ($g, $f) => $g('create', PeriodeInventarisasi::class), ['super-admin', 'admin-bmn']],
        'inventarisasi — pindai (ruangannya)' => [fn ($g, $f) => $g('pindai', $f['invRuangan']), ['super-admin', 'admin-bmn', 'pic-ruangan']],
        'inventarisasi — sahkan berita acara' => [fn ($g, $f) => $g('sahkan', $f['periodeTutup']), ['super-admin', 'pejabat-penatausahaan']],
        'penghapusan — buat usulan' => [fn ($g, $f) => $g('create', UsulanPenghapusan::class), ['super-admin', 'admin-bmn']],
        'penghapusan — putuskan internal' => [fn ($g, $f) => $g('putuskan', $f['usulan']), ['super-admin', 'pejabat-penatausahaan']],
        'laporan — lihat' => [fn ($g, $f) => $g('laporan.lihat'), $staf],
        'laporan — ekspor' => [fn ($g, $f) => $g('laporan.ekspor'), $staf],
        'data pribadi — izin' => [fn ($g, $f) => $g('data-pribadi.lihat'), ['super-admin', 'admin-bmn', 'pejabat-penatausahaan', 'pic-ruangan']],
        'pengaturan sistem' => [fn ($g, $f) => $g('pengaturan.kelola'), ['super-admin']],
        'pengguna — tambah/penugasan PIC' => [fn ($g, $f) => $g('create', User::class), ['super-admin', 'admin-bmn']],
        'pengguna — ubah akun admin' => [fn ($g, $f) => $g('update', $f['pengguna']['admin-bmn']), ['super-admin']],
    ];
});

it('menegakkan matriks hak akses PRD §3.1', function (Closure $cek, array $boleh) {
    $f = mxFixture();

    foreach (MX_PERAN as $peran) {
        $pengguna = $f['pengguna'][$peran];
        $hasil = $cek(fn (string $ability, mixed $args = []) => Gate::forUser($pengguna)->allows($ability, $args), $f);

        expect($hasil)->toBe(in_array($peran, $boleh, true), "peran {$peran}");
    }
})->with('matriks');

it('PIC ruangan lain tidak dapat mengubah kondisi, mencetak label, atau mencatat peminjaman aset ruangan ini', function () {
    $f = mxFixture();
    $picLain = User::factory()->create(['aktif' => true]);
    $picLain->assignRole('pic-ruangan');
    $picLain->ruanganDikelola()->attach($f['lain']->id);

    foreach (['ubahKondisi', 'cetakLabel'] as $ability) {
        expect(Gate::forUser($picLain)->allows($ability, $f['aset']))->toBeFalse($ability);
    }

    expect(Gate::forUser($picLain)->allows('catat', [Peminjaman::class, $f['aset']]))->toBeFalse()
        ->and(Gate::forUser($picLain)->allows('kelola', $f['tiket']))->toBeFalse();
});
