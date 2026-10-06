<?php

use App\Actions\Dbr\TandaiDbrPerluDiperbarui;
use App\Actions\Mutasi\AjukanMutasi;
use App\Actions\Mutasi\BatalkanMutasi;
use App\Actions\Mutasi\SetujuiMutasi;
use App\Actions\Mutasi\TolakMutasi;
use App\Enums\StatusAset;
use App\Enums\StatusMutasi;
use App\Enums\StatusPeminjaman;
use App\Exceptions\FiturNonaktif;
use App\Filament\Resources\Aset\Pages\DaftarAset;
use App\Filament\Resources\Mutasi\Pages\DaftarMutasi;
use App\Filament\Resources\Mutasi\Pages\LihatMutasi;
use App\Livewire\Pindai;
use App\Models\Aset;
use App\Models\Mutasi;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\Pengaturan;
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

function pegawai(string $peran, array $ruangan = []): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');
    $user->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));

    return $user;
}

function ruangMt(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

/** @return array{0: Ruangan, 1: Ruangan, 2: User, 3: User} asal, tujuan, PIC asal, admin */
function skenario(): array
{
    $asal = ruangMt('R-ASAL');
    $tujuan = ruangMt('R-TUJUAN');

    return [$asal, $tujuan, pegawai('pic-ruangan', [$asal]), pegawai('admin-bmn')];
}

it('menjalankan alur penuh: PIC mengajukan, admin menyetujui, lokasi berpindah dan riwayat tercatat (US-MUT-01)', function () {
    [$asal, $tujuan, $pic, $admin] = skenario();
    $a = Aset::factory()->diRuangan($asal)->create(['kode_barang' => '3100102002', 'nup' => 21]);
    $b = Aset::factory()->diRuangan($asal)->create();
    $spy = Mockery::spy(TandaiDbrPerluDiperbarui::class);
    app()->instance(TandaiDbrPerluDiperbarui::class, $spy);

    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [$a->id, $b->id], 'Dipindah ke laboratorium baru', $pic);

    expect($mutasi->nomor)->toBe('MUT-'.now()->year.'-0001')
        ->and($mutasi->status)->toBe(StatusMutasi::Diajukan)
        ->and($mutasi->diajukan_oleh)->toBe($pic->id)
        ->and($mutasi->aset()->count())->toBe(2)
        ->and($a->fresh()->ruangan_id)->toBe($asal->id); // belum berubah sebelum disetujui

    app(SetujuiMutasi::class)->handle($mutasi, $admin, 'Disetujui');

    $a->refresh();
    expect($a->ruangan_id)->toBe($tujuan->id)
        ->and($b->fresh()->ruangan_id)->toBe($tujuan->id)
        ->and($mutasi->fresh())->status->toBe(StatusMutasi::Disetujui)->diputuskan_oleh->toBe($admin->id)->catatan_keputusan->toBe('Disetujui')
        ->and($mutasi->fresh()->diputuskan_pada)->not->toBeNull();

    $riwayat = $a->riwayatLokasi()->first();
    expect($riwayat)->dari_ruangan_id->toBe($asal->id)->ke_ruangan_id->toBe($tujuan->id)->sumber->toBe('mutasi')
        ->mutasi_id->toBe($mutasi->id)->oleh->toBe($admin->id);

    // identitas tidak pernah dinomori ulang (R-17)
    expect($a->kode_barang)->toBe('3100102002')->and($a->nup)->toBe(21);

    // DBR kedua ruangan ditandai perlu diperbarui
    $spy->shouldHaveReceived('handle')->with($asal->id)->atLeast()->once();     // dari Action dan observer Aset
    $spy->shouldHaveReceived('handle')->with($tujuan->id)->atLeast()->once();
});

it('memberi nomor berurutan MUT-{tahun}-{4 digit}', function () {
    [$asal, $tujuan, $pic] = skenario();
    $nomor = collect(range(1, 3))->map(fn () => app(AjukanMutasi::class)
        ->handle($asal, $tujuan, [Aset::factory()->diRuangan($asal)->create()->id], 'x', $pic)->nomor);

    expect($nomor->all())->toBe(collect([1, 2, 3])->map(fn ($n) => 'MUT-'.now()->year.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT))->all());
});

it('hanya satu dari dua persetujuan bersamaan yang berlaku', function () {
    [$asal, $tujuan, $pic, $admin1] = skenario();
    $admin2 = pegawai('admin-bmn');
    $aset = Aset::factory()->diRuangan($asal)->create();
    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', $pic);

    // dua admin membuka halaman yang sama (status di memori masih "diajukan")
    $salinan1 = Mutasi::query()->find($mutasi->id);
    $salinan2 = Mutasi::query()->find($mutasi->id);

    app(SetujuiMutasi::class)->handle($salinan1, $admin1);

    expect(fn () => app(SetujuiMutasi::class)->handle($salinan2, $admin2))->toThrow(ValidationException::class, 'sudah diputuskan');
    expect($aset->riwayatLokasi()->count())->toBe(1)
        ->and($mutasi->fresh()->diputuskan_oleh)->toBe($admin1->id);
});

it('persetujuan bersifat atomik: satu aset tidak lagi layak membatalkan seluruh mutasi', function () {
    [$asal, $tujuan, $pic, $admin] = skenario();
    [$a, $b] = [Aset::factory()->diRuangan($asal)->create(), Aset::factory()->diRuangan($asal)->create()];
    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [$a->id, $b->id], 'x', $pic);

    $b->update(['status' => StatusAset::DalamPerbaikan]);

    expect(fn () => app(SetujuiMutasi::class)->handle($mutasi, $admin))->toThrow(ValidationException::class);
    expect($a->fresh()->ruangan_id)->toBe($asal->id)
        ->and($a->riwayatLokasi()->count())->toBe(0)
        ->and($mutasi->fresh()->status)->toBe(StatusMutasi::Diajukan);
});

it('menolak aset yang sedang dipinjam atau dalam perbaikan saat mengajukan', function (string $keadaan) {
    [$asal, $tujuan, $pic] = skenario();
    $aset = Aset::factory()->diRuangan($asal)->create(['status' => $keadaan === 'perbaikan' ? 'dalam_perbaikan' : 'aktif']);

    if ($keadaan === 'dipinjam') {
        bikinPeminjamanBerjalan($aset);
    }

    expect(fn () => app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', $pic))->toThrow(ValidationException::class);
    expect(Mutasi::count())->toBe(0);
})->with(['perbaikan', 'dipinjam']);

it('menolak aset diusulkan hapus, hilang, dan dihapus', function (string $status) {
    [$asal, $tujuan, $pic] = skenario();
    $aset = Aset::factory()->diRuangan($asal)->create(['status' => $status, 'nomor_sk_penghapusan' => 'SK', 'tanggal_sk_penghapusan' => '2026-01-01', 'kondisi' => 'RB']);

    expect(fn () => app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', $pic))->toThrow(ValidationException::class);
})->with(['diusulkan_hapus', 'hilang', 'dihapus']);

it('menolak persetujuan bila aset menjadi dipinjam setelah pengajuan', function () {
    [$asal, $tujuan, $pic, $admin] = skenario();
    $aset = Aset::factory()->diRuangan($asal)->create();
    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', $pic);

    bikinPeminjamanBerjalan($aset);

    expect(fn () => app(SetujuiMutasi::class)->handle($mutasi, $admin))->toThrow(ValidationException::class, 'sedang dipinjam');
    expect($aset->fresh()->ruangan_id)->toBe($asal->id);
});

it('menolak aset dari ruangan lain, ruangan sama, alasan kosong, dan daftar kosong', function () {
    [$asal, $tujuan, $pic] = skenario();
    $dariLain = Aset::factory()->diRuangan($tujuan)->create();
    $milik = Aset::factory()->diRuangan($asal)->create();
    $aksi = app(AjukanMutasi::class);

    expect(fn () => $aksi->handle($asal, $tujuan, [$dariLain->id], 'x', $pic))->toThrow(ValidationException::class)
        ->and(fn () => $aksi->handle($asal, $asal, [$milik->id], 'x', $pic))->toThrow(ValidationException::class)
        ->and(fn () => $aksi->handle($asal, $tujuan, [$milik->id], '   ', $pic))->toThrow(ValidationException::class)
        ->and(fn () => $aksi->handle($asal, $tujuan, [], 'x', $pic))->toThrow(ValidationException::class)
        ->and(fn () => $aksi->handle($asal, $tujuan, [fake()->uuid()], 'x', $pic))->toThrow(ValidationException::class);
});

it('menolak pengajuan kedua untuk aset yang masih menunggu keputusan', function () {
    [$asal, $tujuan, $pic, $admin] = skenario();
    $aset = Aset::factory()->diRuangan($asal)->create();
    $pertama = app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', $pic);

    expect(fn () => app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'y', $pic))->toThrow(ValidationException::class, 'menunggu keputusan');

    app(TolakMutasi::class)->handle($pertama, $admin, 'Belum perlu');
    expect(app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'y', $pic)->nomor)->toBe('MUT-'.now()->year.'-0002');
});

it('PIC ruangan lain dan peran tanpa izin tidak dapat mengajukan (BR-05)', function () {
    [$asal, $tujuan] = skenario();
    $aset = Aset::factory()->diRuangan($asal)->create();
    $picLain = pegawai('pic-ruangan', [$tujuan]);

    expect(fn () => app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', $picLain))->toThrow(AuthorizationException::class);

    foreach (['pimpinan', 'pejabat-penatausahaan', 'civitas'] as $peran) {
        expect(fn () => app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', pegawai($peran)))->toThrow(AuthorizationException::class);
    }

    // admin boleh dari ruangan mana pun
    expect(app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', pegawai('admin-bmn')))->toBeInstanceOf(Mutasi::class);
});

it('hanya mutasi.putuskan yang dapat menyetujui atau menolak; PIC tidak', function () {
    [$asal, $tujuan, $pic] = skenario();
    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [Aset::factory()->diRuangan($asal)->create()->id], 'x', $pic);

    expect(fn () => app(SetujuiMutasi::class)->handle($mutasi, $pic))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TolakMutasi::class)->handle($mutasi, $pic, 'x'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetujuiMutasi::class)->handle($mutasi, pegawai('pejabat-penatausahaan')))->toThrow(AuthorizationException::class);
});

it('menolak mutasi dengan catatan wajib dan tidak mengubah aset', function () {
    [$asal, $tujuan, $pic, $admin] = skenario();
    $aset = Aset::factory()->diRuangan($asal)->create();
    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', $pic);

    expect(fn () => app(TolakMutasi::class)->handle($mutasi, $admin, '  '))->toThrow(ValidationException::class);

    app(TolakMutasi::class)->handle($mutasi, $admin, 'Ruang tujuan penuh');

    expect($mutasi->fresh())->status->toBe(StatusMutasi::Ditolak)->catatan_keputusan->toBe('Ruang tujuan penuh')->diputuskan_oleh->toBe($admin->id)
        ->and($aset->fresh()->ruangan_id)->toBe($asal->id)
        ->and($aset->riwayatLokasi()->count())->toBe(0);
    expect(fn () => app(SetujuiMutasi::class)->handle($mutasi, $admin))->toThrow(ValidationException::class);
});

it('pengaju dapat membatalkan pengajuan; orang lain tidak', function () {
    [$asal, $tujuan, $pic] = skenario();
    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [Aset::factory()->diRuangan($asal)->create()->id], 'x', $pic);

    expect(fn () => app(BatalkanMutasi::class)->handle($mutasi, pegawai('pic-ruangan', [$asal])))->toThrow(AuthorizationException::class);

    app(BatalkanMutasi::class)->handle($mutasi, $pic);
    expect($mutasi->fresh()->status)->toBe(StatusMutasi::Dibatalkan);
    expect(fn () => app(BatalkanMutasi::class)->handle($mutasi, $pic))->toThrow(AuthorizationException::class);
});

it('toggle fitur mutasi dimatikan menolak pengajuan dan persetujuan (BR-22)', function () {
    [$asal, $tujuan, $pic, $admin] = skenario();
    $aset = Aset::factory()->diRuangan($asal)->create();
    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', $pic);

    Pengaturan::simpan('fitur_mutasi', false);

    expect(fn () => app(AjukanMutasi::class)->handle($asal, $tujuan, [Aset::factory()->diRuangan($asal)->create()->id], 'x', $pic))->toThrow(FiturNonaktif::class)
        ->and(fn () => app(SetujuiMutasi::class)->handle($mutasi, $admin))->toThrow(FiturNonaktif::class);
});

it('mencatat mutasi di jejak audit dengan pelaku dari autentikasi', function () {
    [$asal, $tujuan, $pic] = skenario();
    $this->actingAs($pic);
    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [Aset::factory()->diRuangan($asal)->create()->id], 'x', $pic);

    $log = DB::table('activity_log')->where('subject_id', $mutasi->id)->where('log_name', 'mutasi')->first();
    expect($log->causer_id)->toBe($pic->id);
});

it('daftar mutasi hanya menampilkan yang berkaitan dengan PIC; admin melihat semua', function () {
    [$asal, $tujuan, $pic, $admin] = skenario();
    $lain1 = ruangMt('R-X');
    $lain2 = ruangMt('R-Y');
    $milik = app(AjukanMutasi::class)->handle($asal, $tujuan, [Aset::factory()->diRuangan($asal)->create()->id], 'x', $pic);
    $asing = app(AjukanMutasi::class)->handle($lain1, $lain2, [Aset::factory()->diRuangan($lain1)->create()->id], 'y', $admin);
    $masuk = app(AjukanMutasi::class)->handle($lain1, $asal, [Aset::factory()->diRuangan($lain1)->create()->id], 'z', $admin);

    $this->actingAs($pic);
    Livewire::test(DaftarMutasi::class)->assertCanSeeTableRecords([$milik, $masuk])->assertCanNotSeeTableRecords([$asing]);
    $this->get('/admin/mutasi/'.$asing->id)->assertNotFound();

    $this->actingAs($admin);
    Livewire::test(DaftarMutasi::class)->assertCanSeeTableRecords([$milik, $asing, $masuk]);
});

it('halaman mutasi: admin melihat tombol putuskan dan menyetujui; PIC hanya batalkan', function () {
    [$asal, $tujuan, $pic, $admin] = skenario();
    $aset = Aset::factory()->diRuangan($asal)->create();
    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'x', $pic);

    $this->actingAs($pic);
    Livewire::test(LihatMutasi::class, ['record' => $mutasi->getRouteKey()])
        ->assertActionHidden('setujui')->assertActionHidden('tolak')->assertActionVisible('batalkan');

    $this->actingAs($admin);
    Livewire::test(LihatMutasi::class, ['record' => $mutasi->getRouteKey()])
        ->assertActionVisible('setujui')->assertActionVisible('tolak')
        ->callAction('setujui', ['catatan' => 'ok']);

    expect($aset->fresh()->ruangan_id)->toBe($tujuan->id);

    Livewire::test(LihatMutasi::class, ['record' => $mutasi->getRouteKey()])
        ->assertActionHidden('setujui')->assertActionHidden('batalkan');
});

it('halaman mutasi: penolakan memerlukan alasan', function () {
    [$asal, $tujuan, $pic, $admin] = skenario();
    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [Aset::factory()->diRuangan($asal)->create()->id], 'x', $pic);
    $this->actingAs($admin);

    Livewire::test(LihatMutasi::class, ['record' => $mutasi->getRouteKey()])
        ->callAction('tolak', ['catatan' => ''])->assertHasActionErrors(['catatan' => 'required']);
    expect($mutasi->fresh()->status)->toBe(StatusMutasi::Diajukan);

    Livewire::test(LihatMutasi::class, ['record' => $mutasi->getRouteKey()])
        ->callAction('tolak', ['catatan' => 'Tidak sesuai']);

    expect($mutasi->fresh()->status)->toBe(StatusMutasi::Ditolak);
});

it('PIC mengajukan dari daftar mutasi dengan ruangan asal terbatas pada ruangannya', function () {
    [$asal, $tujuan, $pic] = skenario();
    $aset = Aset::factory()->diRuangan($asal)->create();
    $this->actingAs($pic);

    Livewire::test(DaftarMutasi::class)
        ->callAction('ajukanMutasi', ['ruangan_asal_id' => $asal->id, 'aset' => [$aset->id], 'ruangan_tujuan_id' => $tujuan->id, 'alasan' => 'Pindah lab'])
        ->assertHasNoActionErrors();

    expect(Mutasi::query()->first())->alasan->toBe('Pindah lab')->diajukan_oleh->toBe($pic->id);

    // ruangan di luar tugasnya bukan pilihan sah
    Livewire::test(DaftarMutasi::class)
        ->callAction('ajukanMutasi', ['ruangan_asal_id' => $tujuan->id, 'aset' => [], 'ruangan_tujuan_id' => $asal->id, 'alasan' => 'x'])
        ->assertHasActionErrors();
});

it('aksi di halaman aset: ajukan mutasi satu aset dan massal', function () {
    [$asal, $tujuan, $pic] = skenario();
    $a = Aset::factory()->diRuangan($asal)->create();
    $lainRuang = ruangMt('R-Z');
    $b = Aset::factory()->diRuangan($lainRuang)->create();
    $this->actingAs($pic);

    Livewire::test(DaftarAset::class)
        ->assertActionVisible(TestAction::make('ajukanMutasi')->table($a))
        ->assertActionHidden(TestAction::make('ajukanMutasi')->table($b))
        ->callAction(TestAction::make('ajukanMutasi')->table($a), ['ruangan_tujuan_id' => $tujuan->id, 'alasan' => 'Satu aset']);

    expect(Mutasi::count())->toBe(1);

    $c = Aset::factory()->diRuangan($asal)->create();
    $d = Aset::factory()->diRuangan($asal)->create();
    Livewire::test(DaftarAset::class)
        ->selectTableRecords([$c->id, $d->id])
        ->callAction(TestAction::make('ajukanMutasiMassal')->table()->bulk(), ['ruangan_tujuan_id' => $tujuan->id, 'alasan' => 'Massal']);

    expect(Mutasi::query()->where('alasan', 'Massal')->first()->aset()->count())->toBe(2);
});

it('/pindai: PIC mengajukan mutasi aset di ruangannya, bukan aset ruangan lain', function () {
    [$asal, $tujuan, $pic] = skenario();
    $milik = Aset::factory()->diRuangan($asal)->create();
    $lain = Aset::factory()->diRuangan($tujuan)->create();
    $this->actingAs($pic);

    Livewire::test(Pindai::class)->set('teks', $milik->id)->call('cari')
        ->assertSee('Ajukan mutasi lokasi')
        ->set('tujuanMutasi', $tujuan->id)->set('alasanMutasi', 'Lewat pemindai')
        ->call('ajukanMutasi')
        ->assertSee('MUT-'.now()->year.'-0001');

    Livewire::test(Pindai::class)->set('teks', $lain->id)->call('cari')->assertDontSee('Ajukan mutasi lokasi');
});

/** Satu peminjaman berstatus dipinjam yang memuat aset. */
function bikinPeminjamanBerjalan(Aset $aset): void
{
    Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->untuk($aset)->create();
}
