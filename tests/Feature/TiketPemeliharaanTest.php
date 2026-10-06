<?php

use App\Actions\Pemeliharaan\BukaTiketPemeliharaan;
use App\Actions\Pemeliharaan\SelesaikanTiket;
use App\Actions\Pemeliharaan\UbahStatusTiket;
use App\Actions\Peminjaman\CekKetersediaan;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusTiket;
use App\Filament\Resources\Aset\Pages\DaftarAset;
use App\Filament\Resources\TiketPemeliharaan\Pages\DaftarTiket;
use App\Filament\Resources\TiketPemeliharaan\Pages\LihatTiket;
use App\Models\Aset;
use App\Models\Ruangan;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function pemel(string $peran, array $ruangan = []): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $user;
}

function ruangPm(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

it('membuka tiket dengan nomor TKT-{tahun}-{4 digit} berurutan dan status baru', function () {
    $aset = Aset::factory()->create();
    $pic = pemel('admin-bmn');

    $a = app(BukaTiketPemeliharaan::class)->handle($aset, 'pic', null, 'Kaki meja patah', $pic);
    $b = app(BukaTiketPemeliharaan::class)->handle($aset, 'pic', null, 'Laci macet', $pic);

    expect($a)->nomor->toBe('TKT-'.now()->year.'-0001')->status->toBe(StatusTiket::Baru)->sumber->toBe('pic')->deskripsi->toBe('Kaki meja patah')
        ->and($b->nomor)->toBe('TKT-'.now()->year.'-0002');
});

it('hanya menandai aset dalam perbaikan bila diminta dan aset masih aktif; riwayat status tercatat', function () {
    $pic = pemel('admin-bmn');
    $biasa = Aset::factory()->create();
    $perbaikan = Aset::factory()->create();
    $hilang = Aset::factory()->create(['status' => 'hilang']);
    $buka = app(BukaTiketPemeliharaan::class);

    $buka->handle($biasa, 'pic', null, 'x', $pic);
    $buka->handle($perbaikan, 'pic', null, 'x', $pic, dalamPerbaikan: true);
    $buka->handle($hilang, 'pic', null, 'x', $pic, dalamPerbaikan: true);

    expect($biasa->fresh()->status)->toBe(StatusAset::Aktif)
        ->and($perbaikan->fresh()->status)->toBe(StatusAset::DalamPerbaikan)
        ->and($perbaikan->riwayatStatus()->first())->ke->toBe('dalam_perbaikan')->dari->toBe('aktif')
        ->and($hilang->fresh()->status)->toBe(StatusAset::Hilang);
});

it('aset dalam perbaikan tidak dapat dipinjam sampai tiket selesai (BR-11)', function () {
    $aset = Aset::factory()->dapatDipinjam()->create();
    $pic = pemel('admin-bmn');
    $cek = app(CekKetersediaan::class);

    expect($cek->tersedia($aset, now()->addDay(), now()->addDays(2)))->toBeTrue();

    $tiket = app(BukaTiketPemeliharaan::class)->handle($aset, 'pic', null, 'Rusak', $pic, dalamPerbaikan: true);
    expect($cek->tersedia($aset->fresh(), now()->addDay(), now()->addDays(2)))->toBeFalse();

    app(SelesaikanTiket::class)->handle($tiket, $pic, 'Diganti', '150000', KondisiAset::Baik);
    expect($cek->tersedia($aset->fresh(), now()->addDay(), now()->addDays(2)))->toBeTrue();
});

it('idempoten per (aset, sumber, sumber_id) dan menolak sumber atau deskripsi tidak valid', function () {
    $aset = Aset::factory()->create();
    $buka = app(BukaTiketPemeliharaan::class);
    $sumberId = fake()->uuid();

    $a = $buka->handle($aset, 'peminjaman', $sumberId, 'Rusak saat kembali');
    $b = $buka->handle($aset, 'peminjaman', $sumberId, 'Rusak saat kembali');

    expect($b->id)->toBe($a->id)->and(TiketPemeliharaan::count())->toBe(1);
    expect($buka->handle($aset, 'peminjaman', fake()->uuid(), 'lain')->id)->not->toBe($a->id);
    expect(fn () => $buka->handle($aset, 'ngawur', null, 'x'))->toThrow(ValidationException::class)
        ->and(fn () => $buka->handle($aset, 'pic', null, '  '))->toThrow(ValidationException::class);
});

it('alur: baru → diproses → menunggu suku cadang → diproses → selesai dengan tindakan, biaya DECIMAL, dan kondisi akhir', function () {
    $r = ruangPm('R-1');
    $pic = pemel('pic-ruangan', [$r]);
    $aset = Aset::factory()->diRuangan($r)->create(['kondisi' => 'RR']);
    $tiket = app(BukaTiketPemeliharaan::class)->handle($aset, 'pic', null, 'Layar retak', $pic, dalamPerbaikan: true);
    $ubah = app(UbahStatusTiket::class);

    $ubah->handle($tiket, StatusTiket::Diproses, $pic);
    $ubah->handle($tiket, StatusTiket::MenungguSukuCadang, $pic);
    $ubah->handle($tiket, StatusTiket::Diproses, $pic);
    expect($tiket->fresh())->status->toBe(StatusTiket::Diproses)->ditangani_oleh->toBe($pic->id);

    app(SelesaikanTiket::class)->handle($tiket, $pic, 'Ganti layar', '1250000.5', KondisiAset::Baik);

    $tiket->refresh();
    expect($tiket)->status->toBe(StatusTiket::Selesai)->tindakan->toBe('Ganti layar')->biaya->toBe('1250000.50')
        ->and($tiket->selesai_pada)->not->toBeNull()
        ->and($aset->fresh())->kondisi->toBe(KondisiAset::Baik)->status->toBe(StatusAset::Aktif);

    $riwayat = $aset->riwayatKondisi()->first();
    expect($riwayat)->dari->toBe('RR')->ke->toBe('B')->sumber->toBe('laporan_kerusakan')->sumber_id->toBe($tiket->id)->oleh->toBe($pic->id)
        ->and($aset->riwayatStatus()->first())->ke->toBe('aktif');
});

it('menolak transisi tiket yang tidak sah dan penutupan ganda', function () {
    $pic = pemel('admin-bmn');
    $tiket = app(BukaTiketPemeliharaan::class)->handle(Aset::factory()->create(), 'pic', null, 'x', $pic);
    $ubah = app(UbahStatusTiket::class);

    expect(fn () => $ubah->handle($tiket, StatusTiket::MenungguSukuCadang, $pic))->toThrow(ValidationException::class)
        ->and(fn () => $ubah->handle($tiket, StatusTiket::Selesai, $pic))->toThrow(ValidationException::class)
        ->and(fn () => $ubah->handle($tiket, StatusTiket::Baru, $pic))->toThrow(ValidationException::class);

    app(SelesaikanTiket::class)->handle($tiket, $pic, 'ok');
    expect(fn () => app(SelesaikanTiket::class)->handle($tiket, $pic, 'lagi'))->toThrow(ValidationException::class, 'sudah ditutup')
        ->and(fn () => $ubah->handle($tiket, StatusTiket::Diproses, $pic))->toThrow(ValidationException::class);
});

it('memvalidasi biaya, tindakan, dan hasil penutupan', function () {
    $pic = pemel('admin-bmn');
    $tiket = app(BukaTiketPemeliharaan::class)->handle(Aset::factory()->create(), 'pic', null, 'x', $pic);
    $selesai = app(SelesaikanTiket::class);

    expect(fn () => $selesai->handle($tiket, $pic, 'ok', '-5'))->toThrow(ValidationException::class)
        ->and(fn () => $selesai->handle($tiket, $pic, 'ok', '12.345'))->toThrow(ValidationException::class)
        ->and(fn () => $selesai->handle($tiket, $pic, 'ok', 'abc'))->toThrow(ValidationException::class)
        ->and(fn () => $selesai->handle($tiket, $pic, '  '))->toThrow(ValidationException::class)
        ->and(fn () => $selesai->handle($tiket, $pic, 'ok', null, null, StatusTiket::Diproses))->toThrow(ValidationException::class);

    expect($selesai->handle($tiket, $pic, 'ok', '0')->biaya)->toBe('0.00');
});

it('tidak dapat diperbaiki → aset menjadi Rusak Berat dan kembali aktif tanpa tiket lain', function () {
    $pic = pemel('admin-bmn');
    $aset = Aset::factory()->create();
    $tiket = app(BukaTiketPemeliharaan::class)->handle($aset, 'pic', null, 'Mati total', $pic, dalamPerbaikan: true);

    app(SelesaikanTiket::class)->handle($tiket, $pic, 'Tidak ekonomis diperbaiki', null, null, StatusTiket::TidakDapatDiperbaiki);

    expect($tiket->fresh())->status->toBe(StatusTiket::TidakDapatDiperbaiki)->biaya->toBeNull()
        ->and($aset->fresh())->kondisi->toBe(KondisiAset::RusakBerat)->status->toBe(StatusAset::Aktif);
});

it('aset tetap dalam perbaikan bila masih ada tiket aktif lain', function () {
    $pic = pemel('admin-bmn');
    $aset = Aset::factory()->create();
    $buka = app(BukaTiketPemeliharaan::class);
    $t1 = $buka->handle($aset, 'pic', null, 'Masalah 1', $pic, dalamPerbaikan: true);
    $t2 = $buka->handle($aset, 'pic', null, 'Masalah 2', $pic);

    app(SelesaikanTiket::class)->handle($t1, $pic, 'Beres 1');
    expect($aset->fresh()->status)->toBe(StatusAset::DalamPerbaikan);

    app(SelesaikanTiket::class)->handle($t2, $pic, 'Beres 2');
    expect($aset->fresh()->status)->toBe(StatusAset::Aktif);
});

it('PIC hanya mengelola tiket aset di ruangannya (BR-05); peran lain tidak', function () {
    [$r1, $r2] = [ruangPm('R-1'), ruangPm('R-2')];
    $pic1 = pemel('pic-ruangan', [$r1]);
    $milik = app(BukaTiketPemeliharaan::class)->handle(Aset::factory()->diRuangan($r1)->create(), 'publik', null, 'x');
    $lain = app(BukaTiketPemeliharaan::class)->handle(Aset::factory()->diRuangan($r2)->create(), 'publik', null, 'y');

    expect(fn () => app(UbahStatusTiket::class)->handle($lain, StatusTiket::Diproses, $pic1))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SelesaikanTiket::class)->handle($lain, $pic1, 'x'))->toThrow(AuthorizationException::class)
        ->and(app(UbahStatusTiket::class)->handle($milik, StatusTiket::Diproses, $pic1)->status)->toBe(StatusTiket::Diproses);

    foreach (['pimpinan', 'pejabat-penatausahaan', 'civitas'] as $peran) {
        expect(fn () => app(UbahStatusTiket::class)->handle($milik, StatusTiket::MenungguSukuCadang, pemel($peran)))->toThrow(AuthorizationException::class);
    }
});

it('panel: PIC melihat tiket ruangannya saja; data pelapor hanya untuk yang berhak (BR-23)', function () {
    [$r1, $r2] = [ruangPm('R-1'), ruangPm('R-2')];
    $pic1 = pemel('pic-ruangan', [$r1]);
    $buka = app(BukaTiketPemeliharaan::class);
    $milik = $buka->handle(Aset::factory()->diRuangan($r1)->create(), 'publik', null, 'x', null, false, ['nama_pelapor' => 'Pelapor Rahasia', 'kontak_pelapor' => '0899123', 'ip_hash' => str_repeat('a', 64)]);
    $lain = $buka->handle(Aset::factory()->diRuangan($r2)->create(), 'publik', null, 'y');

    $this->actingAs($pic1);
    Livewire::test(DaftarTiket::class)->assertCanSeeTableRecords([$milik])->assertCanNotSeeTableRecords([$lain]);
    $this->get('/admin/tiket-pemeliharaan/'.$lain->id)->assertNotFound();
    $this->get('/admin/tiket-pemeliharaan/'.$milik->id)->assertOk()->assertSee('Pelapor Rahasia');

    expect($pic1->can('lihatDataPelapor', $milik))->toBeTrue()
        ->and(pemel('pic-ruangan')->can('lihatDataPelapor', $milik))->toBeFalse()
        ->and(pemel('pimpinan')->can('view', $milik))->toBeFalse();
});

it('panel: halaman tiket menyelesaikan tiket lewat aksi', function () {
    $r = ruangPm('R-1');
    $pic = pemel('pic-ruangan', [$r]);
    $aset = Aset::factory()->diRuangan($r)->create(['kondisi' => 'RR']);
    $tiket = app(BukaTiketPemeliharaan::class)->handle($aset, 'pic', null, 'Retak', $pic, dalamPerbaikan: true);
    $this->actingAs($pic);

    Livewire::test(LihatTiket::class, ['record' => $tiket->getRouteKey()])
        ->assertActionVisible('proses')->assertActionHidden('menunggu')->assertActionVisible('selesaikan')
        ->callAction('proses');
    expect($tiket->fresh()->status)->toBe(StatusTiket::Diproses);

    Livewire::test(LihatTiket::class, ['record' => $tiket->getRouteKey()])
        ->assertActionVisible('menunggu')->assertActionHidden('proses')
        ->callAction('selesaikan', ['tindakan' => 'Ganti layar', 'biaya' => '500000', 'kondisi' => 'B']);

    expect($tiket->fresh())->status->toBe(StatusTiket::Selesai)->biaya->toBe('500000.00')
        ->and($aset->fresh())->kondisi->toBe(KondisiAset::Baik)->status->toBe(StatusAset::Aktif);

    Livewire::test(LihatTiket::class, ['record' => $tiket->getRouteKey()])->assertActionHidden('selesaikan')->assertActionHidden('proses');
});

it('panel: daftar tiket punya tab Aktif/Ditutup/Semua', function () {
    $admin = pemel('admin-bmn');
    $buka = app(BukaTiketPemeliharaan::class);
    $aktif = $buka->handle(Aset::factory()->create(), 'pic', null, 'a', $admin);
    $tutup = $buka->handle(Aset::factory()->create(), 'pic', null, 'b', $admin);
    app(SelesaikanTiket::class)->handle($tutup, $admin, 'ok');
    $this->actingAs($admin);

    Livewire::test(DaftarTiket::class)->assertCanSeeTableRecords([$aktif])->assertCanNotSeeTableRecords([$tutup])
        ->set('activeTab', 'selesai')->assertCanSeeTableRecords([$tutup])->assertCanNotSeeTableRecords([$aktif])
        ->set('activeTab', 'semua')->assertCanSeeTableRecords([$aktif, $tutup]);
});

it('panel: aksi Buka tiket pada baris aset hanya untuk PIC ruangannya dan admin', function () {
    [$r1, $r2] = [ruangPm('R-1'), ruangPm('R-2')];
    $pic = pemel('pic-ruangan', [$r1]);
    $milik = Aset::factory()->diRuangan($r1)->create();
    $lain = Aset::factory()->diRuangan($r2)->create();
    $this->actingAs($pic);

    Livewire::test(DaftarAset::class)
        ->assertActionVisible(TestAction::make('bukaTiket')->table($milik))
        ->assertActionHidden(TestAction::make('bukaTiket')->table($lain))
        ->callAction(TestAction::make('bukaTiket')->table($milik), ['deskripsi' => 'Pintu rusak', 'dalam_perbaikan' => true]);

    expect(TiketPemeliharaan::query()->where('aset_id', $milik->id)->count())->toBe(1)
        ->and($milik->fresh()->status)->toBe(StatusAset::DalamPerbaikan)
        ->and($lain->fresh()->status)->toBe(StatusAset::Aktif);
});

it('mencatat tiket di jejak audit tanpa hash IP', function () {
    $pic = pemel('admin-bmn');
    $this->actingAs($pic);
    $tiket = app(BukaTiketPemeliharaan::class)->handle(Aset::factory()->create(), 'publik', null, 'x', null, false, ['ip_hash' => str_repeat('b', 64)]);

    $log = DB::table('activity_log')->where('subject_id', $tiket->id)->where('log_name', 'tiketpemeliharaan')->first();
    expect($log)->not->toBeNull()->and($log->properties)->not->toContain(str_repeat('b', 64))->not->toContain('ip_hash');
});
