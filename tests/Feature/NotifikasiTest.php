<?php

use App\Actions\Mutasi\AjukanMutasi;
use App\Actions\Mutasi\SetujuiMutasi;
use App\Actions\Pemeliharaan\TerimaLaporanKerusakan;
use App\Actions\Peminjaman\AjukanPeminjaman;
use App\Actions\Peminjaman\SetujuiPeminjaman;
use App\Enums\StatusDbr;
use App\Enums\StatusPeriodeInventarisasi;
use App\Models\Aset;
use App\Models\DbrVersi;
use App\Models\Peminjaman;
use App\Models\PeriodeInventarisasi;
use App\Models\Ruangan;
use App\Models\User;
use App\Notifications\BeritaAcaraMenungguPengesahan;
use App\Notifications\DbrMenungguPengesahan;
use App\Notifications\LaporanKerusakanBaru;
use App\Notifications\MutasiDiajukan;
use App\Notifications\MutasiDiputuskan;
use App\Notifications\PeminjamanDiputuskan;
use App\Notifications\PengajuanPeminjamanBaru;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Notification::fake();
});

function ntUser(string $peran, array $ruangan = []): User
{
    $u = User::factory()->create(['aktif' => true]);
    $u->assignRole($peran);
    $u->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));

    return $u;
}

function ntRuangan(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => 'Ruang '.$kode, 'dapat_dipinjam' => true]);
}

test('pengajuan peminjaman memberi tahu PIC ruangan; keputusan memberi tahu peminjam', function () {
    $r = ntRuangan('N-1');
    $pic = ntUser('pic-ruangan', [$r]);
    $lain = ntUser('pic-ruangan', [ntRuangan('N-2')]);
    $civitas = ntUser('civitas');
    $admin = ntUser('admin-bmn');
    $aset = Aset::factory()->diRuangan($r)->create(['dapat_dipinjam' => true, 'kode_barang' => '3100102001', 'nup' => 1]);

    $p = app(AjukanPeminjaman::class)->handle([$aset->id], 'Kuliah tamu', now()->addDay(), now()->addDays(2), $civitas);

    Notification::assertSentTo($pic, PengajuanPeminjamanBaru::class);
    Notification::assertNotSentTo($lain, PengajuanPeminjamanBaru::class);
    Notification::assertNotSentTo($admin, PengajuanPeminjamanBaru::class);

    app(SetujuiPeminjaman::class)->handle($p, $pic, 'Silakan');

    Notification::assertSentTo($civitas, PeminjamanDiputuskan::class, fn ($n) => str_contains($n->isi(), 'Disetujui') && str_contains($n->isi(), 'Silakan'));
});

test('ruangan tanpa PIC: pengajuan jatuh ke admin BMN', function () {
    $r = ntRuangan('N-3');
    $admin = ntUser('admin-bmn');
    $aset = Aset::factory()->diRuangan($r)->create(['dapat_dipinjam' => true, 'kode_barang' => '3100102001', 'nup' => 1]);

    app(AjukanPeminjaman::class)->handle([$aset->id], 'Rapat', now()->addDay(), now()->addDays(2), ntUser('civitas'));

    Notification::assertSentTo($admin, PengajuanPeminjamanBaru::class);
});

test('mutasi: admin diberi tahu saat diajukan, PIC asal dan tujuan saat diputuskan', function () {
    [$asal, $tujuan] = [ntRuangan('N-4'), ntRuangan('N-5')];
    $picAsal = ntUser('pic-ruangan', [$asal]);
    $picTujuan = ntUser('pic-ruangan', [$tujuan]);
    $admin = ntUser('admin-bmn');
    $aset = Aset::factory()->diRuangan($asal)->create(['kode_barang' => '3100102001', 'nup' => 1]);

    $m = app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'Penataan', $picAsal);
    Notification::assertSentTo($admin, MutasiDiajukan::class);

    app(SetujuiMutasi::class)->handle($m, $admin);
    Notification::assertSentTo($picAsal, MutasiDiputuskan::class);
    Notification::assertSentTo($picTujuan, MutasiDiputuskan::class);
});

test('laporan kerusakan memberi tahu PIC ruangan aset', function () {
    $r = ntRuangan('N-6');
    $pic = ntUser('pic-ruangan', [$r]);
    $aset = Aset::factory()->diRuangan($r)->create(['kode_barang' => '3100102001', 'nup' => 1]);

    app(TerimaLaporanKerusakan::class)->handle($aset, 'Layar retak parah', 'Anon');

    Notification::assertSentTo($pic, LaporanKerusakanBaru::class);
});

test('notifikasi diantrekan ke notifikasi, via database dan mail, tanpa WhatsApp saat nonaktif', function () {
    $n = new PengajuanPeminjamanBaru(Peminjaman::factory()->make());
    $u = User::factory()->make(['email' => 'a@unsil.ac.id']);

    expect($n->queue)->toBe('notifikasi')->and($n->via($u))->toBe(['database', 'mail']);
});

test('DBR disetujui PIC dan periode ditutup memberi tahu pejabat penatausahaan saja', function () {
    $r = ntRuangan('N-7');
    $pejabat = ntUser('pejabat-penatausahaan');
    $admin = ntUser('admin-bmn');
    $dbr = DbrVersi::query()->create(['ruangan_id' => $r->id, 'versi' => 1, 'status' => StatusDbr::Draf, 'snapshot' => ['aset' => []]]);

    $dbr->update(['status' => StatusDbr::DisetujuiPic]);
    Notification::assertSentTo($pejabat, DbrMenungguPengesahan::class);
    Notification::assertNotSentTo($admin, DbrMenungguPengesahan::class);

    $periode = PeriodeInventarisasi::query()->create(['nama' => 'Semester Ganjil', 'mulai' => '2026-10-01', 'status' => StatusPeriodeInventarisasi::Berjalan]);
    $periode->update(['status' => StatusPeriodeInventarisasi::Ditutup]);
    Notification::assertSentTo($pejabat, BeritaAcaraMenungguPengesahan::class);
});
