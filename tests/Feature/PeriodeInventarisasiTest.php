<?php

use App\Actions\Inventarisasi\BuatPeriode;
use App\Actions\Inventarisasi\BukaPeriode;
use App\Actions\Inventarisasi\TugaskanPetugas;
use App\Actions\Mutasi\AjukanMutasi;
use App\Actions\Mutasi\SetujuiMutasi;
use App\Enums\JenisInventarisasi;
use App\Enums\StatusInventarisasiRuangan;
use App\Enums\StatusPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\Pages\BuatPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\Pages\DaftarPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\Pages\LihatPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\RelationManagers\RuanganInventarisasiRelationManager;
use App\Models\Aset;
use App\Models\Mutasi;
use App\Models\PeriodeInventarisasi;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\Pengaturan;
use Carbon\Carbon;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['aset.lock_tunggu_detik' => 0]);
});

function invUser(string $peran, array $ruangan = []): User
{
    $u = User::factory()->create();
    $u->assignRole($peran);
    $u->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));
    $u->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $u;
}

function invRuang(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

function invPeriode(User $admin, array $ruangan, string $nama = 'Inventarisasi 2026'): PeriodeInventarisasi
{
    return app(BuatPeriode::class)->handle($nama, JenisInventarisasi::Sensus, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'), collect($ruangan)->pluck('id')->all(), $admin);
}

it('admin membuat periode rencana beserta ruangan berstatus belum', function () {
    [$r1, $r2] = [invRuang('R-1'), invRuang('R-2')];
    $admin = invUser('admin-bmn');

    $p = invPeriode($admin, [$r1, $r2, $r1]);   // duplikat diabaikan

    expect($p)->nama->toBe('Inventarisasi 2026')->status->toBe(StatusPeriodeInventarisasi::Rencana)->jenis->toBe(JenisInventarisasi::Sensus)
        ->and($p->mulai->toDateString())->toBe('2026-11-01')->and($p->selesai_rencana->toDateString())->toBe('2026-11-30')
        ->and($p->ruangan)->toHaveCount(2)
        ->and($p->ruangan->pluck('status')->unique()->all())->toBe([StatusInventarisasiRuangan::Belum]);
});

it('memvalidasi nama, ruangan, dan tanggal; hanya yang berhak membuat', function () {
    $r = invRuang('R-1');
    $admin = invUser('admin-bmn');
    $buat = app(BuatPeriode::class);
    $mulai = Carbon::parse('2026-11-01');

    expect(fn () => $buat->handle(' ', JenisInventarisasi::Sensus, $mulai, null, [$r->id], $admin))->toThrow(ValidationException::class)
        ->and(fn () => $buat->handle('X', JenisInventarisasi::Sensus, $mulai, null, [], $admin))->toThrow(ValidationException::class)
        ->and(fn () => $buat->handle('X', JenisInventarisasi::Sensus, $mulai, null, [fake()->uuid()], $admin))->toThrow(ValidationException::class)
        ->and(fn () => $buat->handle('X', JenisInventarisasi::Sensus, $mulai, Carbon::parse('2026-10-01'), [$r->id], $admin))->toThrow(ValidationException::class);

    foreach (['pic-ruangan', 'pimpinan', 'pejabat-penatausahaan', 'civitas'] as $peran) {
        expect(fn () => $buat->handle('X', JenisInventarisasi::Sensus, $mulai, null, [$r->id], invUser($peran)))->toThrow(AuthorizationException::class);
    }
    expect(PeriodeInventarisasi::count())->toBe(0);
});

it('membuka periode: rencana → berjalan', function () {
    $admin = invUser('admin-bmn');
    $p = invPeriode($admin, [invRuang('R-1')]);

    $hasil = app(BukaPeriode::class)->handle($p, $admin);

    expect($hasil)->status->toBe(StatusPeriodeInventarisasi::Berjalan)->and($hasil->dibuka_pada)->not->toBeNull();
    expect(fn () => app(BukaPeriode::class)->handle($p, $admin))->toThrow(ValidationException::class, 'hanya yang berstatus rencana');
});

it('hanya satu periode berjalan dalam satu waktu (BR-14)', function () {
    $admin = invUser('admin-bmn');
    $r = invRuang('R-1');
    $a = invPeriode($admin, [$r], 'Periode A');
    $b = invPeriode($admin, [$r], 'Periode B');

    app(BukaPeriode::class)->handle($a, $admin);

    expect(fn () => app(BukaPeriode::class)->handle($b, $admin))->toThrow(ValidationException::class, 'Periode A');
    expect(PeriodeInventarisasi::query()->berjalan()->count())->toBe(1)->and($b->fresh()->status)->toBe(StatusPeriodeInventarisasi::Rencana);

    // setelah periode pertama ditutup, yang kedua boleh dibuka
    $a->update(['status' => StatusPeriodeInventarisasi::Ditutup]);
    expect(app(BukaPeriode::class)->handle($b, $admin)->status)->toBe(StatusPeriodeInventarisasi::Berjalan);
});

it('pembukaan periode dijaga lock: proses lain yang sedang membuka membuat permintaan ditolak rapi', function () {
    $admin = invUser('admin-bmn');
    $p = invPeriode($admin, [invRuang('R-1')]);

    $lock = Cache::lock('aset:inventarisasi:buka', 10);
    expect($lock->get())->toBeTrue();

    expect(fn () => app(BukaPeriode::class)->handle($p, $admin))->toThrow(ValidationException::class, 'sedang berjalan');
    $lock->release();

    expect(app(BukaPeriode::class)->handle($p, $admin)->status)->toBe(StatusPeriodeInventarisasi::Berjalan);
});

it('periode tanpa ruangan dan peran tanpa izin tidak dapat dibuka', function () {
    $admin = invUser('admin-bmn');
    $p = invPeriode($admin, [invRuang('R-1')]);
    $p->ruangan()->delete();

    expect(fn () => app(BukaPeriode::class)->handle($p, $admin))->toThrow(ValidationException::class, 'belum memiliki ruangan');

    $q = invPeriode($admin, [invRuang('R-2')]);
    foreach (['pic-ruangan', 'pejabat-penatausahaan', 'pimpinan'] as $peran) {
        expect(fn () => app(BukaPeriode::class)->handle($q, invUser($peran)))->toThrow(AuthorizationException::class);
    }
});

it('menugaskan petugas per ruangan: sinkron tim dan petugas utama', function () {
    $r = invRuang('R-1');
    $admin = invUser('admin-bmn');
    $pic1 = invUser('pic-ruangan', [$r]);
    $pic2 = invUser('pic-ruangan');
    $inv = invPeriode($admin, [$r])->ruangan->first();

    app(TugaskanPetugas::class)->handle($inv, [$pic1->id, $pic2->id], $admin);
    expect($inv->petugas()->pluck('users.id')->sort()->values()->all())->toBe(collect([$pic1->id, $pic2->id])->sort()->values()->all())
        ->and($inv->fresh()->petugas_id)->not->toBeNull();

    app(TugaskanPetugas::class)->handle($inv, [$pic2->id], $admin);
    expect($inv->petugas()->pluck('users.id')->all())->toBe([$pic2->id])->and($inv->fresh()->petugas_id)->toBe($pic2->id);
});

it('penugasan menolak akun tanpa hak pindai, nonaktif, tak ada, ruangan selesai, dan pemanggil tanpa izin', function () {
    $r = invRuang('R-1');
    $admin = invUser('admin-bmn');
    $inv = invPeriode($admin, [$r])->ruangan->first();
    $tugas = app(TugaskanPetugas::class);

    expect(fn () => $tugas->handle($inv, [invUser('civitas')->id], $admin))->toThrow(ValidationException::class, 'admin-bmn atau pic-ruangan')
        ->and(fn () => $tugas->handle($inv, [invUser('pimpinan')->id], $admin))->toThrow(ValidationException::class)
        ->and(fn () => $tugas->handle($inv, [fake()->uuid()], $admin))->toThrow(ValidationException::class)
        ->and(fn () => $tugas->handle($inv, [User::factory()->create(['aktif' => false])->id], $admin))->toThrow(ValidationException::class)
        ->and(fn () => $tugas->handle($inv, [invUser('pic-ruangan')->id], invUser('pic-ruangan', [$r])))->toThrow(AuthorizationException::class);

    $inv->update(['status' => StatusInventarisasiRuangan::Selesai]);
    expect(fn () => $tugas->handle($inv, [invUser('pic-ruangan')->id], $admin))->toThrow(ValidationException::class, 'sudah selesai');
});

it('mutasi pada ruangan yang sedang diinventarisasi ditahan; ruangan lain dan sesudah selesai tidak (BR-14)', function () {
    [$r1, $r2, $r3] = [invRuang('R-1'), invRuang('R-2'), invRuang('R-3')];
    $admin = invUser('admin-bmn');
    $aset = Aset::factory()->diRuangan($r1)->count(3)->create();
    $p = invPeriode($admin, [$r1]);

    // periode masih rencana → belum menahan
    $m0 = app(AjukanMutasi::class)->handle($r1, $r3, [$aset[0]->id], 'Sebelum', $admin);
    app(AjukanMutasi::class)->handle($r3, $r1, [Aset::factory()->diRuangan($r3)->create()->id], 'Masuk', $admin);

    app(BukaPeriode::class)->handle($p, $admin);

    expect(fn () => app(AjukanMutasi::class)->handle($r1, $r2, [$aset[1]->id], 'Keluar', $admin))->toThrow(ValidationException::class, 'sedang diinventarisasi')
        ->and(fn () => app(AjukanMutasi::class)->handle($r2, $r1, [Aset::factory()->diRuangan($r2)->create()->id], 'Masuk', $admin))->toThrow(ValidationException::class, 'Ruang R-1')
        ->and(fn () => app(SetujuiMutasi::class)->handle($m0, $admin))->toThrow(ValidationException::class, 'ditahan');

    // mutasi antar ruangan yang tidak diinventarisasi tetap boleh
    expect(app(AjukanMutasi::class)->handle($r2, $r3, [Aset::factory()->diRuangan($r2)->create()->id], 'Bebas', $admin)->nomor)->toStartWith('MUT-');

    // ruangan selesai diinventarisasi → mutasi kembali boleh
    $p->ruangan()->first()->update(['status' => StatusInventarisasiRuangan::Selesai]);
    expect(app(SetujuiMutasi::class)->handle($m0, $admin)->status->value)->toBe('disetujui');
});

it('penahanan mutasi mengikuti toggle fitur', function () {
    [$r1, $r2] = [invRuang('R-1'), invRuang('R-2')];
    $admin = invUser('admin-bmn');
    $aset = Aset::factory()->diRuangan($r1)->create();
    app(BukaPeriode::class)->handle(invPeriode($admin, [$r1]), $admin);

    Pengaturan::simpan('fitur_tahan_mutasi_saat_inventarisasi', false);

    expect(app(AjukanMutasi::class)->handle($r1, $r2, [$aset->id], 'Boleh', $admin))->toBeInstanceOf(Mutasi::class);
});

it('Policy pindai: PIC ruangan itu dan petugas ditugaskan boleh saat periode berjalan; lainnya tidak', function () {
    [$r1, $r2] = [invRuang('R-1'), invRuang('R-2')];
    $admin = invUser('admin-bmn');
    $picR1 = invUser('pic-ruangan', [$r1]);
    $picR2 = invUser('pic-ruangan', [$r2]);
    $p = invPeriode($admin, [$r1]);
    $inv = $p->ruangan->first();

    expect($picR1->can('pindai', $inv))->toBeFalse();       // periode belum berjalan

    app(BukaPeriode::class)->handle($p, $admin);
    $inv = $inv->fresh();

    expect($picR1->can('pindai', $inv))->toBeTrue()
        ->and($admin->can('pindai', $inv))->toBeTrue()
        ->and($picR2->can('pindai', $inv))->toBeFalse()
        ->and(invUser('pimpinan')->can('pindai', $inv))->toBeFalse()
        ->and(invUser('civitas')->can('pindai', $inv))->toBeFalse();

    app(TugaskanPetugas::class)->handle($inv, [$picR2->id], $admin);
    expect($picR2->fresh()->can('pindai', $inv->fresh()))->toBeTrue();
});

it('panel: admin membuat periode lewat form lalu membuka; PIC hanya melihat periode ruangannya', function () {
    [$r1, $r2] = [invRuang('R-1'), invRuang('R-2')];
    $admin = invUser('admin-bmn');
    $this->actingAs($admin);

    Livewire::test(BuatPeriodeInventarisasi::class)
        ->fillForm(['nama' => 'Sensus 2026', 'jenis' => 'sensus', 'mulai' => '2026-11-01', 'selesai_rencana' => '2026-11-30', 'ruangan' => [$r1->id]])
        ->call('create')->assertHasNoFormErrors();

    $milik = PeriodeInventarisasi::query()->where('nama', 'Sensus 2026')->firstOrFail();
    $asing = invPeriode($admin, [$r2], 'Periode Lain');

    Livewire::test(LihatPeriodeInventarisasi::class, ['record' => $milik->getRouteKey()])->assertActionVisible('buka')->callAction('buka');
    expect($milik->fresh()->status)->toBe(StatusPeriodeInventarisasi::Berjalan);

    $this->actingAs(invUser('pic-ruangan', [$r1]));
    Livewire::test(DaftarPeriodeInventarisasi::class)->assertCanSeeTableRecords([$milik])->assertCanNotSeeTableRecords([$asing]);
    $this->get('/admin/inventarisasi/'.$asing->id)->assertNotFound();

    Livewire::test(LihatPeriodeInventarisasi::class, ['record' => $milik->getRouteKey()])->assertActionHidden('buka');
});

it('panel: admin menugaskan petugas dari relation manager; PIC tidak melihat aksinya', function () {
    $r = invRuang('R-1');
    $admin = invUser('admin-bmn');
    $pic = invUser('pic-ruangan', [$r]);
    $p = invPeriode($admin, [$r]);
    $inv = $p->ruangan->first();

    $this->actingAs($admin);
    Livewire::test(RuanganInventarisasiRelationManager::class, ['ownerRecord' => $p, 'pageClass' => LihatPeriodeInventarisasi::class])
        ->assertActionVisible(TestAction::make('tugaskan')->table($inv))
        ->callAction(TestAction::make('tugaskan')->table($inv), ['petugas' => [$pic->id]])->assertHasNoActionErrors();
    expect($inv->petugas()->pluck('users.id')->all())->toBe([$pic->id]);

    $this->actingAs($pic);
    Livewire::test(RuanganInventarisasiRelationManager::class, ['ownerRecord' => $p, 'pageClass' => LihatPeriodeInventarisasi::class])
        ->assertActionHidden(TestAction::make('tugaskan')->table($inv));
});
