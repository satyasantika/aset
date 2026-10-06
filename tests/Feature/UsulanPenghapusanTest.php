<?php

use App\Actions\Penghapusan\AjukanUsulan;
use App\Actions\Penghapusan\BatalkanUsulan;
use App\Actions\Penghapusan\BuatUsulanPenghapusan;
use App\Actions\Penghapusan\CatatSkPenghapusan;
use App\Actions\Penghapusan\SetujuiUsulan;
use App\Actions\Penghapusan\TolakUsulan;
use App\Enums\StatusAset;
use App\Enums\StatusUsulanHapus;
use App\Exceptions\FiturNonaktif;
use App\Filament\Resources\Penghapusan\Pages\DaftarUsulan;
use App\Filament\Resources\Penghapusan\Pages\LihatUsulan;
use App\Filament\Resources\Penghapusan\UsulanPenghapusanResource;
use App\Models\Aset;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use App\Support\Pengaturan;
use Carbon\Carbon;
use Database\Seeders\PeranDanIzinSeeder;
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

function hpsUser(string $peran): User
{
    $u = User::factory()->create();
    $u->assignRole($peran);
    $u->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $u;
}

function rusakBerat(array $atribut = []): Aset
{
    return Aset::factory()->create(['kondisi' => 'RB', ...$atribut]);
}

function hilangAset(array $atribut = []): Aset
{
    return Aset::factory()->create(['status' => 'hilang', ...$atribut]);
}

it('membuat usulan draf berisi aset Rusak Berat dan hilang dengan nomor USL-{tahun}-{4 digit} (US-HPS-01)', function () {
    $admin = hpsUser('admin-bmn');
    [$rb, $hilang] = [rusakBerat(), hilangAset()];

    $u = app(BuatUsulanPenghapusan::class)->handle([$rb->id, $hilang->id, $rb->id], 'Tidak layak pakai', $admin);

    expect($u)->nomor->toBe('USL-'.now()->year.'-0001')->status->toBe(StatusUsulanHapus::Draf)->alasan->toBe('Tidak layak pakai')->pengusul_id->toBe($admin->id)
        ->and($u->item)->toHaveCount(2)
        ->and($u->item->firstWhere('aset_id', $rb->id)->alasan_item)->toBe('rusak_berat')
        ->and($u->item->firstWhere('aset_id', $hilang->id)->alasan_item)->toBe('hilang')
        ->and($rb->fresh()->status)->toBe(StatusAset::Aktif);   // belum berubah pada draf

    expect(app(BuatUsulanPenghapusan::class)->handle([rusakBerat()->id], 'x', $admin)->nomor)->toBe('USL-'.now()->year.'-0002');
});

it('aset Baik, Rusak Ringan, dalam perbaikan, dan dihapus tidak dapat diusulkan (BR-16)', function (array $atribut) {
    $admin = hpsUser('admin-bmn');
    $aset = Aset::factory()->create($atribut);

    expect(fn () => app(BuatUsulanPenghapusan::class)->handle([$aset->id], 'x', $admin))->toThrow(ValidationException::class, 'tidak dapat diusulkan');
    expect(UsulanPenghapusan::count())->toBe(0);
})->with([
    'baik' => [['kondisi' => 'B']],
    'rusak ringan' => [['kondisi' => 'RR']],
    'rusak berat tetapi dalam perbaikan' => [['kondisi' => 'RB', 'status' => 'dalam_perbaikan']],
    'sudah diusulkan hapus' => [['kondisi' => 'RB', 'status' => 'diusulkan_hapus']],
    'sudah dihapus' => [['kondisi' => 'RB', 'status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK', 'tanggal_sk_penghapusan' => '2026-01-01']],
]);

it('satu aset campuran layak/tak layak menggagalkan seluruh usulan', function () {
    $admin = hpsUser('admin-bmn');

    expect(fn () => app(BuatUsulanPenghapusan::class)->handle([rusakBerat()->id, Aset::factory()->create()->id], 'x', $admin))->toThrow(ValidationException::class);
    expect(UsulanPenghapusan::count())->toBe(0);
});

it('aset tidak boleh berada di dua usulan terbuka; setelah dibatalkan boleh lagi', function () {
    $admin = hpsUser('admin-bmn');
    $rb = rusakBerat();
    $u1 = app(BuatUsulanPenghapusan::class)->handle([$rb->id], 'x', $admin);

    expect(fn () => app(BuatUsulanPenghapusan::class)->handle([$rb->id], 'y', $admin))->toThrow(ValidationException::class, 'sudah ada dalam usulan');

    app(BatalkanUsulan::class)->handle($u1, $admin);
    expect(app(BuatUsulanPenghapusan::class)->handle([$rb->id], 'y', $admin))->toBeInstanceOf(UsulanPenghapusan::class);
});

it('memvalidasi alasan dan daftar aset; hanya penghapusan.kelola yang dapat membuat; toggle fitur dibaca server', function () {
    $admin = hpsUser('admin-bmn');
    $rb = rusakBerat();

    expect(fn () => app(BuatUsulanPenghapusan::class)->handle([$rb->id], '  ', $admin))->toThrow(ValidationException::class)
        ->and(fn () => app(BuatUsulanPenghapusan::class)->handle([], 'x', $admin))->toThrow(ValidationException::class)
        ->and(fn () => app(BuatUsulanPenghapusan::class)->handle([fake()->uuid()], 'x', $admin))->toThrow(ValidationException::class);

    foreach (['pic-ruangan', 'pejabat-penatausahaan', 'pimpinan', 'civitas'] as $peran) {
        expect(fn () => app(BuatUsulanPenghapusan::class)->handle([$rb->id], 'x', hpsUser($peran)))->toThrow(AuthorizationException::class);
    }

    Pengaturan::simpan('fitur_hapus_aset', false);
    expect(fn () => app(BuatUsulanPenghapusan::class)->handle([$rb->id], 'x', $admin))->toThrow(FiturNonaktif::class);
});

it('alur penuh: draf → diajukan → disetujui internal (aset diusulkan_hapus) → SK terbit (aset dihapus ber-SK)', function () {
    $admin = hpsUser('admin-bmn');
    $pejabat = hpsUser('pejabat-penatausahaan');
    [$rb, $hilang] = [rusakBerat(['nama' => 'Printer Rusak']), hilangAset(['nama' => 'Laptop Hilang'])];

    $u = app(BuatUsulanPenghapusan::class)->handle([$rb->id, $hilang->id], 'Penghapusan akhir tahun', $admin);
    app(AjukanUsulan::class)->handle($u, $admin);
    expect($u->fresh()->status)->toBe(StatusUsulanHapus::Diajukan);

    app(SetujuiUsulan::class)->handle($u, $pejabat, 'Disetujui');

    expect($u->fresh())->status->toBe(StatusUsulanHapus::DisetujuiInternal)->pemutus_id->toBe($pejabat->id)->catatan_keputusan->toBe('Disetujui')
        ->and($rb->fresh()->status)->toBe(StatusAset::DiusulkanHapus)->and($hilang->fresh()->status)->toBe(StatusAset::DiusulkanHapus)
        ->and($rb->riwayatStatus()->first())->dari->toBe('aktif')->ke->toBe('diusulkan_hapus')->oleh->toBe($pejabat->id);

    app(CatatSkPenghapusan::class)->handle($u, 'SK-Rektor 123/2026', Carbon::parse('2026-10-01'), 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view', $admin);

    $u->refresh();
    expect($u)->status->toBe(StatusUsulanHapus::SkTerbit)->nomor_sk->toBe('SK-Rektor 123/2026')->and($u->tanggal_sk->toDateString())->toBe('2026-10-01')
        ->and($u->tautanBerkas()->first())->jenis->toBe('sk')->drive_file_id->toBe('1AbCdEfGhIjKlMnOpQrStUv');

    foreach ([$rb, $hilang] as $a) {
        $a = Aset::withTrashed()->find($a->id);
        expect($a)->status->toBe(StatusAset::Dihapus)->nomor_sk_penghapusan->toBe('SK-Rektor 123/2026')
            ->and($a->tanggal_sk_penghapusan->toDateString())->toBe('2026-10-01')
            ->and($a->trashed())->toBeFalse();   // tidak pernah dihapus permanen/lunak
    }
});

it('aset dihapus wajib SK: nomor kosong, tanggal masa depan, atau tautan tidak valid membatalkan seluruh pencatatan', function () {
    $admin = hpsUser('admin-bmn');
    $pejabat = hpsUser('pejabat-penatausahaan');
    $rb = rusakBerat();
    $u = app(BuatUsulanPenghapusan::class)->handle([$rb->id], 'x', $admin);
    app(AjukanUsulan::class)->handle($u, $admin);
    app(SetujuiUsulan::class)->handle($u, $pejabat);
    $sk = app(CatatSkPenghapusan::class);

    expect(fn () => $sk->handle($u, '  ', Carbon::parse('2026-10-01'), null, $admin))->toThrow(ValidationException::class, 'Nomor SK')
        ->and(fn () => $sk->handle($u, 'SK-1', now()->addDay(), null, $admin))->toThrow(ValidationException::class, 'masa depan')
        ->and(fn () => $sk->handle($u, 'SK-1', Carbon::parse('2026-10-01'), 'https://bit.ly/abc', $admin))->toThrow(ValidationException::class);

    // rollback penuh: aset belum dihapus, usulan belum ber-SK
    expect($rb->fresh()->status)->toBe(StatusAset::DiusulkanHapus)->and($u->fresh())->status->toBe(StatusUsulanHapus::DisetujuiInternal)->nomor_sk->toBeNull();
});

it('SK hanya dapat dicatat pada usulan disetujui internal', function (StatusUsulanHapus $status) {
    $admin = hpsUser('admin-bmn');
    $u = app(BuatUsulanPenghapusan::class)->handle([rusakBerat()->id], 'x', $admin);
    $u->update(['status' => $status]);

    expect(fn () => app(CatatSkPenghapusan::class)->handle($u, 'SK-1', Carbon::parse('2026-10-01'), null, $admin))->toThrow(ValidationException::class, 'setelah disetujui internal');
})->with([StatusUsulanHapus::Draf, StatusUsulanHapus::Diajukan, StatusUsulanHapus::SkTerbit, StatusUsulanHapus::Dibatalkan]);

it('otorisasi tahap: pejabat saja yang memutuskan; admin saja yang mengajukan dan mencatat SK', function () {
    $admin = hpsUser('admin-bmn');
    $pejabat = hpsUser('pejabat-penatausahaan');
    $u = app(BuatUsulanPenghapusan::class)->handle([rusakBerat()->id], 'x', $admin);

    expect(fn () => app(AjukanUsulan::class)->handle($u, $pejabat))->toThrow(AuthorizationException::class);
    app(AjukanUsulan::class)->handle($u, $admin);

    expect(fn () => app(SetujuiUsulan::class)->handle($u, $admin))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetujuiUsulan::class)->handle($u, hpsUser('pic-ruangan')))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TolakUsulan::class)->handle($u, $admin, 'x'))->toThrow(AuthorizationException::class);

    app(SetujuiUsulan::class)->handle($u, $pejabat);
    expect(fn () => app(CatatSkPenghapusan::class)->handle($u, 'SK-1', Carbon::parse('2026-10-01'), null, $pejabat))->toThrow(AuthorizationException::class);
});

it('penolakan pejabat mengembalikan ke draf dengan catatan dan tidak mengubah aset', function () {
    $admin = hpsUser('admin-bmn');
    $pejabat = hpsUser('pejabat-penatausahaan');
    $rb = rusakBerat();
    $u = app(BuatUsulanPenghapusan::class)->handle([$rb->id], 'x', $admin);
    app(AjukanUsulan::class)->handle($u, $admin);

    expect(fn () => app(TolakUsulan::class)->handle($u, $pejabat, ' '))->toThrow(ValidationException::class);

    app(TolakUsulan::class)->handle($u, $pejabat, 'Lengkapi berita acara kerusakan');

    expect($u->fresh())->status->toBe(StatusUsulanHapus::Draf)->catatan_keputusan->toBe('Lengkapi berita acara kerusakan')
        ->and($rb->fresh()->status)->toBe(StatusAset::Aktif);
    expect(fn () => app(SetujuiUsulan::class)->handle($u, $pejabat))->toThrow(ValidationException::class, 'hanya yang diajukan');
});

it('pengajuan dan persetujuan mengecek ulang kelayakan aset', function () {
    $admin = hpsUser('admin-bmn');
    $pejabat = hpsUser('pejabat-penatausahaan');
    $rb = rusakBerat();
    $u = app(BuatUsulanPenghapusan::class)->handle([$rb->id], 'x', $admin);

    $rb->update(['kondisi' => 'B']);   // diperbaiki setelah usulan dibuat
    expect(fn () => app(AjukanUsulan::class)->handle($u, $admin))->toThrow(ValidationException::class, 'tidak dapat diusulkan');

    $rb->update(['kondisi' => 'RB']);
    app(AjukanUsulan::class)->handle($u, $admin);
    $rb->update(['kondisi' => 'RR']);
    expect(fn () => app(SetujuiUsulan::class)->handle($u, $pejabat))->toThrow(ValidationException::class);
    expect($u->fresh()->status)->toBe(StatusUsulanHapus::Diajukan);
});

it('pembatalan memulihkan status aset: rusak berat → aktif, hilang → hilang; usulan ber-SK tidak dapat dibatalkan', function () {
    $admin = hpsUser('admin-bmn');
    $pejabat = hpsUser('pejabat-penatausahaan');
    [$rb, $hilang] = [rusakBerat(), hilangAset()];
    $u = app(BuatUsulanPenghapusan::class)->handle([$rb->id, $hilang->id], 'x', $admin);
    app(AjukanUsulan::class)->handle($u, $admin);
    app(SetujuiUsulan::class)->handle($u, $pejabat);
    expect($rb->fresh()->status)->toBe(StatusAset::DiusulkanHapus);

    app(BatalkanUsulan::class)->handle($u, $admin);

    expect($u->fresh()->status)->toBe(StatusUsulanHapus::Dibatalkan)
        ->and($rb->fresh()->status)->toBe(StatusAset::Aktif)->and($hilang->fresh()->status)->toBe(StatusAset::Hilang);

    $sk = app(BuatUsulanPenghapusan::class)->handle([$rb->id], 'x', $admin);
    app(AjukanUsulan::class)->handle($sk, $admin);
    app(SetujuiUsulan::class)->handle($sk, $pejabat);
    app(CatatSkPenghapusan::class)->handle($sk, 'SK-9', Carbon::parse('2026-10-01'), null, $admin);

    expect(fn () => app(BatalkanUsulan::class)->handle($sk, $admin))->toThrow(ValidationException::class, 'tidak dapat dibatalkan');
});

it('transisi status aset: diusulkan_hapus dapat kembali ke aktif atau hilang, tetapi dihapus final', function () {
    expect(StatusAset::DiusulkanHapus->dapatBerpindahKe(StatusAset::Aktif))->toBeTrue()
        ->and(StatusAset::DiusulkanHapus->dapatBerpindahKe(StatusAset::Hilang))->toBeTrue()
        ->and(StatusAset::DiusulkanHapus->dapatBerpindahKe(StatusAset::Dihapus))->toBeTrue()
        ->and(StatusAset::DiusulkanHapus->dapatBerpindahKe(StatusAset::DalamPerbaikan))->toBeFalse()
        ->and(StatusAset::Dihapus->transisiSah())->toBe([]);
});

it('panel: pejabat tidak melihat draf; admin membuat usulan dan pejabat memutuskan lewat aksi', function () {
    $admin = hpsUser('admin-bmn');
    $pejabat = hpsUser('pejabat-penatausahaan');
    $rb = rusakBerat(['nama' => 'Monitor Pecah']);
    Aset::factory()->create(['nama' => 'Monitor Baik']);

    $this->actingAs($admin);
    $opsi = UsulanPenghapusanResource::opsiAsetLayak();
    expect(array_keys($opsi))->toBe([$rb->id]);

    Livewire::test(DaftarUsulan::class)->callAction('buatUsulan', ['aset' => [$rb->id], 'alasan' => 'Pecah total'])->assertHasNoActionErrors();
    $u = UsulanPenghapusan::firstOrFail();
    expect(UsulanPenghapusanResource::opsiAsetLayak())->toBe([]);   // sudah di usulan terbuka

    $this->actingAs($pejabat);
    Livewire::test(DaftarUsulan::class)->assertCanNotSeeTableRecords([$u])->assertActionHidden('buatUsulan');
    $this->get('/admin/usulan-penghapusan/'.$u->id)->assertNotFound();

    $this->actingAs($admin);
    Livewire::test(LihatUsulan::class, ['record' => $u->getRouteKey()])->assertActionVisible('ajukan')->assertActionHidden('setujui')->assertActionHidden('catatSk')->callAction('ajukan');

    $this->actingAs($pejabat);
    Livewire::test(DaftarUsulan::class)->assertCanSeeTableRecords([$u]);
    Livewire::test(LihatUsulan::class, ['record' => $u->getRouteKey()])
        ->assertActionVisible('setujui')->assertActionVisible('tolak')->assertActionHidden('ajukan')->callAction('setujui', ['catatan' => 'ok']);
    expect($rb->fresh()->status)->toBe(StatusAset::DiusulkanHapus);

    $this->actingAs($admin);
    Livewire::test(LihatUsulan::class, ['record' => $u->getRouteKey()])
        ->assertActionVisible('catatSk')->assertActionHidden('setujui')
        ->callAction('catatSk', ['nomor_sk' => 'SK-77/2026', 'tanggal_sk' => '2026-10-01', 'url_sk' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view']);

    expect($u->fresh()->status)->toBe(StatusUsulanHapus::SkTerbit)->and($rb->fresh())->status->toBe(StatusAset::Dihapus)->nomor_sk_penghapusan->toBe('SK-77/2026');
});
