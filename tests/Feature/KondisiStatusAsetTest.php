<?php

use App\Actions\Aset\UbahKondisiAset;
use App\Actions\Aset\UbahStatusAset;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Exceptions\FiturNonaktif;
use App\Filament\Resources\Aset\Pages\DaftarAset;
use App\Filament\Resources\Aset\Pages\LihatAset;
use App\Filament\Resources\Aset\RelationManagers\RiwayatKondisiRelationManager;
use App\Filament\Resources\Aset\RelationManagers\RiwayatLokasiRelationManager;
use App\Filament\Resources\Aset\RelationManagers\RiwayatStatusRelationManager;
use App\Models\Aset;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\Pengaturan;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function orang(string $peran, array $ruangan = []): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');
    $user->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));

    return $user;
}

function ruangKS(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

it('mengubah kondisi dan menulis riwayat (BR-03)', function () {
    $pic = orang('admin-bmn');
    $aset = Aset::factory()->create();

    app(UbahKondisiAset::class)->handle($aset, KondisiAset::RusakRingan, $pic, 'manual', null, 'Kaki patah');

    $riwayat = $aset->riwayatKondisi()->first();
    expect($aset->fresh()->kondisi)->toBe(KondisiAset::RusakRingan)
        ->and($riwayat)->dari->toBe('B')->ke->toBe('RR')->sumber->toBe('manual')->catatan->toBe('Kaki patah')->oleh->toBe($pic->id);
});

it('tidak menulis riwayat bila kondisi tidak berubah', function () {
    $aset = Aset::factory()->create();

    app(UbahKondisiAset::class)->handle($aset, KondisiAset::Baik, orang('admin-bmn'));

    expect($aset->riwayatKondisi()->count())->toBe(0);
});

it('PIC hanya dapat mengubah kondisi aset di ruangannya (BR-05)', function () {
    [$r1, $r2] = [ruangKS('R-1'), ruangKS('R-2')];
    $pic = orang('pic-ruangan', [$r1]);

    app(UbahKondisiAset::class)->handle(Aset::factory()->diRuangan($r1)->create(), KondisiAset::RusakBerat, $pic);

    $lain = Aset::factory()->diRuangan($r2)->create();
    expect(fn () => app(UbahKondisiAset::class)->handle($lain, KondisiAset::RusakBerat, $pic))->toThrow(AuthorizationException::class);
    expect($lain->fresh()->kondisi)->toBe(KondisiAset::Baik)->and($lain->riwayatKondisi()->count())->toBe(0);
});

it('menghormati toggle fitur ubah kondisi hanya untuk perubahan manual (BR-22)', function () {
    Pengaturan::simpan('fitur_ubah_kondisi', false);
    $admin = orang('admin-bmn');
    $aset = Aset::factory()->create();

    expect(fn () => app(UbahKondisiAset::class)->handle($aset, KondisiAset::RusakRingan, $admin))->toThrow(FiturNonaktif::class);

    app(UbahKondisiAset::class)->handle($aset, KondisiAset::RusakRingan, $admin, 'peminjaman', 'p-1');
    expect($aset->fresh()->kondisi)->toBe(KondisiAset::RusakRingan)->and($aset->riwayatKondisi()->first()->sumber)->toBe('peminjaman');
});

it('mengubah status hanya lewat transisi sah dan menulis riwayat', function () {
    $admin = orang('admin-bmn');
    $aset = Aset::factory()->create();

    app(UbahStatusAset::class)->handle($aset, StatusAset::DalamPerbaikan, $admin, 'Servis');
    app(UbahStatusAset::class)->handle($aset, StatusAset::Aktif, $admin);

    expect($aset->fresh()->status)->toBe(StatusAset::Aktif)
        ->and($aset->riwayatStatus()->count())->toBe(2)
        ->and($aset->riwayatStatus()->get()->pluck('ke')->sort()->values()->all())->toBe(['aktif', 'dalam_perbaikan']);

    expect(fn () => app(UbahStatusAset::class)->handle($aset, StatusAset::Dihapus, $admin))->toThrow(ValidationException::class);
    expect($aset->fresh()->status)->toBe(StatusAset::Aktif);
});

it('menolak transisi tidak sah dari matriks diagram status', function (StatusAset $dari, StatusAset $ke) {
    $aset = Aset::factory()->create(['status' => $dari, 'kondisi' => 'RB', 'nomor_sk_penghapusan' => 'SK', 'tanggal_sk_penghapusan' => '2026-01-01']);

    expect(fn () => app(UbahStatusAset::class)->handle($aset, $ke, orang('admin-bmn')))->toThrow(ValidationException::class);
})->with([
    [StatusAset::Aktif, StatusAset::Dihapus],
    [StatusAset::DalamPerbaikan, StatusAset::Hilang],
    [StatusAset::DalamPerbaikan, StatusAset::DiusulkanHapus],
    [StatusAset::Hilang, StatusAset::Dihapus],
    [StatusAset::Dihapus, StatusAset::Aktif],
]);

it('hanya aset Rusak Berat atau Hilang yang dapat diusulkan hapus (BR-16)', function () {
    $admin = orang('admin-bmn');
    $baik = Aset::factory()->create();
    $rusakBerat = Aset::factory()->create(['kondisi' => 'RB']);
    $hilang = Aset::factory()->create(['status' => StatusAset::Hilang]);

    expect(fn () => app(UbahStatusAset::class)->handle($baik, StatusAset::DiusulkanHapus, $admin))->toThrow(ValidationException::class);
    app(UbahStatusAset::class)->handle($rusakBerat, StatusAset::DiusulkanHapus, $admin);
    app(UbahStatusAset::class)->handle($hilang, StatusAset::DiusulkanHapus, $admin);

    expect($rusakBerat->fresh()->status)->toBe(StatusAset::DiusulkanHapus)->and($hilang->fresh()->status)->toBe(StatusAset::DiusulkanHapus);
});

it('menghapus aset hanya dengan nomor dan tanggal SK, dan tidak pernah menghapus baris', function () {
    $admin = orang('admin-bmn');
    $aset = Aset::factory()->create(['status' => StatusAset::DiusulkanHapus, 'kondisi' => 'RB']);

    expect(fn () => app(UbahStatusAset::class)->handle($aset, StatusAset::Dihapus, $admin))->toThrow(ValidationException::class);
    expect($aset->fresh()->status)->toBe(StatusAset::DiusulkanHapus);

    app(UbahStatusAset::class)->handle($aset, StatusAset::Dihapus, $admin, 'SK terbit', ['nomor_sk_penghapusan' => 'SK-77/2026', 'tanggal_sk_penghapusan' => '2026-09-30']);

    $segar = Aset::query()->find($aset->id);
    expect($segar->status)->toBe(StatusAset::Dihapus)
        ->and($segar->nomor_sk_penghapusan)->toBe('SK-77/2026')
        ->and($segar->tanggal_sk_penghapusan->toDateString())->toBe('2026-09-30')
        ->and($segar->trashed())->toBeFalse();
});

it('ubah status hanya untuk aset.kelola; PIC ditolak', function () {
    $r = ruangKS('R-1');
    $pic = orang('pic-ruangan', [$r]);
    $aset = Aset::factory()->diRuangan($r)->create();

    expect(fn () => app(UbahStatusAset::class)->handle($aset, StatusAset::DalamPerbaikan, $pic))->toThrow(AuthorizationException::class);

    // alur internal yang sudah mengotorisasi dapat melewati pemeriksaan
    app(UbahStatusAset::class)->handle($aset, StatusAset::DalamPerbaikan, $pic, null, [], otorisasi: false);
    expect($aset->fresh()->status)->toBe(StatusAset::DalamPerbaikan);
});

it('aksi Filament ubah kondisi: PIC ruangannya berhasil, ruangan lain tidak terlihat', function () {
    [$r1, $r2] = [ruangKS('R-1'), ruangKS('R-2')];
    $this->actingAs(orang('pic-ruangan', [$r1]));
    $milik = Aset::factory()->diRuangan($r1)->create();
    $lain = Aset::factory()->diRuangan($r2)->create();

    Livewire::test(DaftarAset::class)
        ->assertActionVisible(TestAction::make('ubahKondisi')->table($milik))
        ->assertActionHidden(TestAction::make('ubahKondisi')->table($lain))
        ->assertActionHidden(TestAction::make('ubahStatus')->table($milik))
        ->callAction(TestAction::make('ubahKondisi')->table($milik), ['kondisi' => 'RR', 'catatan' => 'Retak']);

    expect($milik->fresh()->kondisi)->toBe(KondisiAset::RusakRingan)->and($lain->fresh()->kondisi)->toBe(KondisiAset::Baik);
});

it('aksi massal ubah kondisi hanya berlaku untuk aset yang diotorisasi', function () {
    [$r1, $r2] = [ruangKS('R-1'), ruangKS('R-2')];
    $this->actingAs(orang('pic-ruangan', [$r1]));
    $milik = Aset::factory()->diRuangan($r1)->count(2)->create();
    $lain = Aset::factory()->diRuangan($r2)->create();

    Livewire::test(DaftarAset::class)
        ->selectTableRecords($milik->pluck('id')->push($lain->id)->all())
        ->callAction(TestAction::make('ubahKondisiMassal')->table()->bulk(), ['kondisi' => 'RB']);

    expect(Aset::query()->where('kondisi', 'RB')->count())->toBe(2)
        ->and($lain->fresh()->kondisi)->toBe(KondisiAset::Baik);
});

it('aksi Filament ubah status menawarkan transisi sah dan meminta SK untuk dihapus', function () {
    $this->actingAs(orang('admin-bmn'));
    $aset = Aset::factory()->create(['status' => StatusAset::DiusulkanHapus, 'kondisi' => 'RB']);

    Livewire::test(DaftarAset::class)
        ->callAction(TestAction::make('ubahStatus')->table($aset), ['status' => 'dihapus'])
        ->assertHasActionErrors(['nomor_sk_penghapusan', 'tanggal_sk_penghapusan']);
    expect($aset->fresh()->status)->toBe(StatusAset::DiusulkanHapus);

    Livewire::test(DaftarAset::class)
        ->callAction(TestAction::make('ubahStatus')->table($aset), ['status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK-1', 'tanggal_sk_penghapusan' => '2026-10-01']);

    expect($aset->fresh()->status)->toBe(StatusAset::Dihapus);
});

it('relation manager riwayat menampilkan data dan tanpa aksi tulis', function () {
    $admin = orang('admin-bmn');
    $this->actingAs($admin);
    $aset = Aset::factory()->create();
    app(UbahKondisiAset::class)->handle($aset, KondisiAset::RusakRingan, $admin);
    app(UbahStatusAset::class)->handle($aset, StatusAset::DalamPerbaikan, $admin);

    foreach ([RiwayatKondisiRelationManager::class, RiwayatStatusRelationManager::class, RiwayatLokasiRelationManager::class] as $kelas) {
        Livewire::test($kelas, ['ownerRecord' => $aset, 'pageClass' => LihatAset::class])
            ->assertSuccessful()
            ->assertTableActionDoesNotExist('create');
    }

    Livewire::test(RiwayatKondisiRelationManager::class, ['ownerRecord' => $aset, 'pageClass' => LihatAset::class])
        ->assertCountTableRecords(1);
});
