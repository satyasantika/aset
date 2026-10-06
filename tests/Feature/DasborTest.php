<?php

use App\Enums\StatusDbr;
use App\Enums\StatusPeminjaman;
use App\Filament\Widgets\RincianAsetWidget;
use App\Filament\Widgets\StatistikAsetWidget;
use App\Models\Aset;
use App\Models\DbrVersi;
use App\Models\KodefikasiBarang;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use App\Support\Dasbor;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Cache::flush();
});

function dsUser(string $peran, array $ruangan = []): User
{
    $u = User::factory()->create();
    $u->assignRole($peran);
    $u->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));
    $u->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $u;
}

/**
 * Data uji berangka tetap.
 * R1: A1 B 1.000.000 (Mebeler) · A2 RR 500.000 (Mebeler) · A3 RB 250.000 (tanpa kategori) · A5 hilang 100.000
 * R2: A4 B 2.000.000 (Elektronik) · A6 dihapus 777.000 (tidak dihitung)
 *
 * @return array{r1: Ruangan, r2: Ruangan, a: array<string, Aset>}
 */
function dsData(): array
{
    KodefikasiBarang::query()->create(['kode' => '3100102001', 'uraian' => 'Meja', 'tingkat' => 5, 'kategori_lokal' => 'Mebeler']);
    KodefikasiBarang::query()->create(['kode' => '3100203001', 'uraian' => 'Personal Computer', 'tingkat' => 5, 'kategori_lokal' => null]);
    [$r1, $r2] = [Ruangan::query()->create(['kode' => 'R-1', 'nama' => 'Ruang Satu']), Ruangan::query()->create(['kode' => 'R-2', 'nama' => 'Ruang Dua'])];
    $a = [
        'a1' => Aset::factory()->diRuangan($r1)->create(['kondisi' => 'B', 'nilai_perolehan' => '1000000', 'kode_barang' => '3100102001', 'nup' => 1]),
        'a2' => Aset::factory()->diRuangan($r1)->create(['kondisi' => 'RR', 'nilai_perolehan' => '500000', 'kode_barang' => '3100102001', 'nup' => 2]),
        'a3' => Aset::factory()->diRuangan($r1)->belumTercatat('INT-3')->create(['kondisi' => 'RB', 'nilai_perolehan' => '250000']),
        'a4' => Aset::factory()->diRuangan($r2)->create(['kondisi' => 'B', 'nilai_perolehan' => '2000000', 'kode_barang' => '3100203001', 'nup' => 4]),
        'a5' => Aset::factory()->diRuangan($r1)->create(['kondisi' => 'B', 'nilai_perolehan' => '100000', 'status' => 'hilang', 'kode_barang' => '3100102001', 'nup' => 5]),
        'a6' => Aset::factory()->diRuangan($r2)->create(['kondisi' => 'B', 'nilai_perolehan' => '777000', 'status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK', 'tanggal_sk_penghapusan' => '2026-01-01', 'kode_barang' => '3100203001', 'nup' => 6]),
    ];

    return ['r1' => $r1, 'r2' => $r2, 'a' => $a];
}

it('menghitung angka dasbor global: jumlah, nilai, kondisi, hilang; aset dihapus tidak dihitung (LAP-02)', function () {
    dsData();

    $s = Dasbor::statistik(dsUser('admin-bmn'));

    expect($s['cakupan'])->toBe('Seluruh fakultas')
        ->and($s['total'])->toBe(['jumlah' => 5, 'nilai' => '3850000.00'])      // A1–A5 (hilang ikut tercatat)
        ->and($s['hilang'])->toBe(1)
        ->and($s['per_kondisi'])->toBe([
            'B' => ['jumlah' => 2, 'nilai' => '3000000.00'],
            'RR' => ['jumlah' => 1, 'nilai' => '500000.00'],
            'RB' => ['jumlah' => 1, 'nilai' => '250000.00'],
        ]);
});

it('rincian per ruangan dan per kategori konsisten dengan angka total (tanpa hilang/dihapus)', function () {
    dsData();

    $s = Dasbor::statistik(dsUser('admin-bmn'));

    $ruangan = collect($s['per_ruangan'])->keyBy('kode');
    expect($ruangan['R-1'])->toMatchArray(['nama' => 'Ruang Satu', 'jumlah' => 3, 'nilai' => '1750000.00', 'B' => 1, 'RR' => 1, 'RB' => 1])
        ->and($ruangan['R-2'])->toMatchArray(['jumlah' => 1, 'nilai' => '2000000.00', 'B' => 1, 'RR' => 0, 'RB' => 0])
        ->and(array_sum(array_column($s['per_ruangan'], 'jumlah')))->toBe($s['total']['jumlah'] - $s['hilang']);

    $kategori = collect($s['per_kategori'])->keyBy('kategori');
    expect($kategori['Mebeler'])->toMatchArray(['jumlah' => 2, 'nilai' => '1500000.00'])
        ->and($kategori['Personal Computer'])->toMatchArray(['jumlah' => 1, 'nilai' => '2000000.00'])   // tanpa kategori_lokal → uraian
        ->and($kategori['Tanpa kategori'])->toMatchArray(['jumlah' => 1, 'nilai' => '250000.00'])
        ->and(array_sum(array_column($s['per_kategori'], 'jumlah')))->toBe(4);
});

it('PIC hanya melihat ruangannya; pimpinan, pejabat, dan admin melihat seluruh fakultas (BR-05)', function () {
    ['r1' => $r1, 'r2' => $r2] = dsData();
    $pic1 = dsUser('pic-ruangan', [$r1]);

    $s = Dasbor::statistik($pic1);
    expect($s['cakupan'])->toBe('Ruangan yang Anda kelola')
        ->and($s['total'])->toBe(['jumlah' => 4, 'nilai' => '1850000.00'])   // A1,A2,A3,A5
        ->and($s['hilang'])->toBe(1)
        ->and(array_column($s['per_ruangan'], 'kode'))->toBe(['R-1'])
        ->and($s['per_kondisi']['B'])->toBe(['jumlah' => 1, 'nilai' => '1000000.00']);

    expect(Dasbor::statistik(dsUser('pic-ruangan'))['total']['jumlah'])->toBe(0);   // PIC tanpa ruangan

    foreach (['pimpinan', 'pejabat-penatausahaan', 'admin-bmn', 'super-admin'] as $peran) {
        expect(Dasbor::statistik(dsUser($peran))['total']['jumlah'])->toBe(5);
    }

    expect(Dasbor::statistik(dsUser('pic-ruangan', [$r2]))['total'])->toBe(['jumlah' => 1, 'nilai' => '2000000.00']);   // A6 dihapus tak dihitung
});

it('menghitung pinjaman aktif/terlambat, tiket terbuka, dan DBR perlu diperbarui menurut cakupan', function () {
    ['r1' => $r1, 'r2' => $r2, 'a' => $a] = dsData();
    $mulai = now()->subDays(5)->toDateTimeString();
    Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->rentang($mulai, now()->addDay()->toDateTimeString())->untuk($a['a1'])->create();           // aktif R1
    Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->rentang($mulai, now()->subDay()->toDateTimeString())->untuk($a['a2'])->create();            // terlambat R1
    Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->rentang($mulai, now()->addDay()->toDateTimeString())->untuk($a['a4'])->create();            // aktif R2
    Peminjaman::factory()->status(StatusPeminjaman::Dikembalikan)->rentang($mulai, now()->subDay()->toDateTimeString())->untuk($a['a1'])->create();       // selesai
    Peminjaman::factory()->status(StatusPeminjaman::Diajukan)->untuk($a['a1'])->create();                                                                // bukan aktif
    foreach ([[$a['a1'], 'baru'], [$a['a4'], 'diproses'], [$a['a3'], 'selesai']] as $i => [$aset, $status]) {
        TiketPemeliharaan::query()->create(['nomor' => 'TKT-X-'.$i, 'aset_id' => $aset->id, 'sumber' => 'pic', 'deskripsi' => 'x', 'status' => $status]);
    }
    DbrVersi::query()->create(['ruangan_id' => $r2->id, 'versi' => 1, 'status' => StatusDbr::PerluDiperbarui, 'snapshot' => ['aset' => []]]);
    DbrVersi::query()->create(['ruangan_id' => $r1->id, 'versi' => 1, 'status' => StatusDbr::Disahkan, 'snapshot' => ['aset' => []]]);

    $global = Dasbor::statistik(dsUser('pimpinan'));
    expect($global)->toMatchArray(['pinjaman_aktif' => 3, 'pinjaman_terlambat' => 1, 'tiket_terbuka' => 2, 'dbr_perlu_diperbarui' => 1]);

    $pic1 = Dasbor::statistik(dsUser('pic-ruangan', [$r1]));
    expect($pic1)->toMatchArray(['pinjaman_aktif' => 2, 'pinjaman_terlambat' => 1, 'tiket_terbuka' => 1, 'dbr_perlu_diperbarui' => 0]);

    $pic2 = Dasbor::statistik(dsUser('pic-ruangan', [$r2]));
    expect($pic2)->toMatchArray(['pinjaman_aktif' => 1, 'pinjaman_terlambat' => 0, 'tiket_terbuka' => 1, 'dbr_perlu_diperbarui' => 1]);
});

it('dasbor hanya berisi angka agregat: tidak memuat nama peminjam atau pelapor (BR-23)', function () {
    ['a' => $a] = dsData();
    Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->untuk($a['a1'])->create(['nama_peminjam' => 'Budi Rahasia', 'kontak_peminjam' => '0899000111']);
    TiketPemeliharaan::query()->create(['nomor' => 'TKT-Y-1', 'aset_id' => $a['a1']->id, 'sumber' => 'publik', 'deskripsi' => 'x', 'nama_pelapor' => 'Pelapor Rahasia', 'kontak_pelapor' => '0877000222']);

    $json = json_encode(Dasbor::statistik(dsUser('pimpinan')));

    expect($json)->not->toContain('Budi')->not->toContain('0899000111')->not->toContain('Pelapor')->not->toContain('0877000222');
});

it('hasil di-cache 30 menit dan dibersihkan saat data berubah (versi cache naik)', function () {
    ['a' => $a] = dsData();
    $admin = dsUser('admin-bmn');
    $pertama = Dasbor::statistik($admin);
    $versi = Cache::get(Dasbor::KUNCI_VERSI);

    expect(Cache::has('aset:statistik:v'.$versi.':global'))->toBeTrue();

    // perubahan di luar model (tanpa observer) tidak terlihat: masih cache
    DB::table('aset')->where('id', $a['a1']->id)->update(['nilai_perolehan' => '9000000']);
    expect(Dasbor::statistik($admin)['total']['nilai'])->toBe($pertama['total']['nilai']);

    // perubahan lewat model membersihkan cache
    $a['a2']->update(['nama' => 'Berubah']);
    expect(Cache::get(Dasbor::KUNCI_VERSI))->toBeGreaterThan($versi)
        ->and(Dasbor::statistik($admin)['total']['nilai'])->toBe('11850000.00')->not->toBe($pertama['total']['nilai']);
});

it('cache kedaluwarsa setelah 30 menit', function () {
    ['a' => $a] = dsData();
    $admin = dsUser('admin-bmn');
    $awal = Dasbor::statistik($admin);
    DB::table('aset')->where('id', $a['a1']->id)->update(['nilai_perolehan' => '5000000']);

    $this->travel(29)->minutes();
    expect(Dasbor::statistik($admin)['total']['nilai'])->toBe($awal['total']['nilai']);

    $this->travel(2)->minutes();
    expect(Dasbor::statistik($admin)['total']['nilai'])->toBe('7850000.00');
});

it('perubahan peminjaman, tiket, DBR, dan ruangan juga membersihkan cache', function () {
    ['a' => $a, 'r1' => $r1] = dsData();
    $admin = dsUser('admin-bmn');
    Dasbor::statistik($admin);
    $versi = Cache::get(Dasbor::KUNCI_VERSI);

    Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->untuk($a['a1'])->create();
    $v2 = Cache::get(Dasbor::KUNCI_VERSI);
    TiketPemeliharaan::query()->create(['nomor' => 'TKT-Z-1', 'aset_id' => $a['a1']->id, 'sumber' => 'pic', 'deskripsi' => 'x']);
    $v3 = Cache::get(Dasbor::KUNCI_VERSI);
    DbrVersi::query()->create(['ruangan_id' => $r1->id, 'versi' => 1, 'status' => StatusDbr::Draf, 'snapshot' => ['aset' => []]]);
    $v4 = Cache::get(Dasbor::KUNCI_VERSI);
    $r1->update(['nama' => 'Ruang Satu Baru']);
    $v5 = Cache::get(Dasbor::KUNCI_VERSI);

    expect($v2)->toBeGreaterThan($versi)->and($v3)->toBeGreaterThan($v2)->and($v4)->toBeGreaterThan($v3)->and($v5)->toBeGreaterThan($v4)
        ->and(Dasbor::statistik($admin)['tiket_terbuka'])->toBe(1);
});

it('cache per cakupan: PIC berbeda tidak berbagi hasil', function () {
    ['r1' => $r1, 'r2' => $r2] = dsData();
    $pic1 = dsUser('pic-ruangan', [$r1]);
    $pic2 = dsUser('pic-ruangan', [$r2]);

    expect(Dasbor::statistik($pic1)['total']['jumlah'])->toBe(4)->and(Dasbor::statistik($pic2)['total']['jumlah'])->toBe(1)
        ->and(Dasbor::statistik($pic1)['total']['jumlah'])->toBe(4);
});

it('widget statistik menampilkan angka dan hanya untuk peran yang boleh melihat aset', function () {
    dsData();

    foreach (['admin-bmn', 'pimpinan', 'pic-ruangan', 'pejabat-penatausahaan', 'super-admin'] as $peran) {
        $this->actingAs(dsUser($peran));
        expect(StatistikAsetWidget::canView())->toBeTrue()->and(RincianAsetWidget::canView())->toBeTrue();
    }

    $this->actingAs(dsUser('civitas'));
    expect(StatistikAsetWidget::canView())->toBeFalse()->and(RincianAsetWidget::canView())->toBeFalse();

    $this->actingAs(dsUser('admin-bmn'));
    Livewire::test(StatistikAsetWidget::class)
        ->assertSee('Seluruh fakultas')->assertSee('Rp 3.850.000')->assertSee('Jumlah aset')->assertSee('1 di antaranya hilang')
        ->assertSee('Rusak Berat')->assertSee('Pinjaman aktif')->assertSee('DBR perlu diperbarui');

    Livewire::test(RincianAsetWidget::class)->assertSee('Ruang Satu')->assertSee('Mebeler')->assertSee('Tanpa kategori');
});

it('dasbor PIC memuat hanya ruangannya di widget', function () {
    ['r1' => $r1] = dsData();
    $this->actingAs(dsUser('pic-ruangan', [$r1]));

    Livewire::test(RincianAsetWidget::class)->assertSee('Ruang Satu')->assertDontSee('Ruang Dua');
    Livewire::test(StatistikAsetWidget::class)->assertSee('Ruangan yang Anda kelola');
});

it('halaman dasbor /admin menampilkan widget untuk admin', function () {
    dsData();
    $this->actingAs(dsUser('admin-bmn'))->get('/admin')->assertOk();
});
