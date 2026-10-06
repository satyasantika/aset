<?php

use App\Actions\Pemeliharaan\BukaTiketPemeliharaan;
use App\Actions\Peminjaman\CekKetersediaan;
use App\Actions\Peminjaman\KembalikanPeminjaman;
use App\Enums\KondisiAset;
use App\Enums\StatusPeminjaman;
use App\Filament\Resources\Peminjaman\Pages\DaftarPeminjaman;
use App\Filament\Resources\Peminjaman\Pages\LihatPeminjaman;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
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

function pj4Pic(Ruangan ...$r): User
{
    $u = User::factory()->create();
    $u->assignRole('pic-ruangan');
    $u->ruanganDikelola()->attach(collect($r)->pluck('id'));
    $u->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $u;
}

function pj4Ruang(string $kode = 'R-1'): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

/** @return array{0: Peminjaman, 1: Aset, 2: Aset} peminjaman dipinjam berisi dua aset kondisi B */
function pj4Dipinjam(Ruangan $r, array $status = []): array
{
    [$a, $b] = [Aset::factory()->diRuangan($r)->dapatDipinjam()->create(), Aset::factory()->diRuangan($r)->dapatDipinjam()->create()];
    $p = Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->rentang(now()->subHour()->toDateTimeString(), now()->addDay()->toDateTimeString())
        ->untuk($a, $b)->create(['dicatat_oleh' => User::factory()->create()->id, ...$status]);

    return [$p, $a, $b];
}

it('menerima pengembalian semua barang dengan kondisi per item (BR-09)', function () {
    $r = pj4Ruang();
    $pic = pj4Pic($r);
    [$p, $a, $b] = pj4Dipinjam($r);

    $hasil = app(KembalikanPeminjaman::class)->handle($p, [$a->id => 'B', $b->id => 'B'], $pic, [$a->id => 'Lengkap']);

    expect($hasil)->status->toBe(StatusPeminjaman::Dikembalikan)->diterima_kembali_oleh->toBe($pic->id)
        ->and($hasil->dikembalikan_pada)->not->toBeNull()
        ->and($hasil->item()->where('aset_id', $a->id)->first())->kondisi_saat_kembali->toBe(KondisiAset::Baik)->catatan->toBe('Lengkap')
        ->and($a->fresh()->sedangDipinjam)->toBeFalse()
        ->and($a->riwayatKondisi()->count())->toBe(0);   // tidak berubah → tanpa riwayat
});

it('pengembalian sebagian ditolak dan tidak mengubah apa pun', function (array $kondisi) {
    $r = pj4Ruang();
    [$p, $a, $b] = pj4Dipinjam($r);
    $masukan = collect($kondisi)->mapWithKeys(fn ($v, $k) => [$k === 'a' ? $a->id : ($k === 'b' ? $b->id : $k) => $v])->all();

    expect(fn () => app(KembalikanPeminjaman::class)->handle($p, $masukan, pj4Pic($r)))->toThrow(ValidationException::class);

    expect($p->fresh()->status)->toBe(StatusPeminjaman::Dipinjam)
        ->and($p->item()->whereNotNull('kondisi_saat_kembali')->count())->toBe(0)
        ->and($a->fresh()->sedangDipinjam)->toBeTrue();
})->with([
    'hanya satu item' => [['a' => 'B']],
    'tanpa kondisi' => [[]],
    'kondisi kosong' => [['a' => 'B', 'b' => '']],
    'kondisi tidak valid' => [['a' => 'B', 'b' => 'X']],
    'item asing ikut' => [['a' => 'B', 'b' => 'B', 'bukan-bagian' => 'B']],
]);

it('kondisi berbeda memperbarui kondisi aset dengan riwayat sumber peminjaman dan membuka tiket untuk RR/RB (BR-10)', function () {
    $r = pj4Ruang();
    $pic = pj4Pic($r);
    [$p, $a, $b] = pj4Dipinjam($r);
    $spy = Mockery::spy(BukaTiketPemeliharaan::class);
    app()->instance(BukaTiketPemeliharaan::class, $spy);

    app(KembalikanPeminjaman::class)->handle($p, [$a->id => 'RR', $b->id => 'B'], $pic);

    expect($a->fresh()->kondisi)->toBe(KondisiAset::RusakRingan)->and($b->fresh()->kondisi)->toBe(KondisiAset::Baik);
    $riwayat = $a->riwayatKondisi()->first();
    expect($riwayat)->dari->toBe('B')->ke->toBe('RR')->sumber->toBe('peminjaman')->sumber_id->toBe($p->id)->oleh->toBe($pic->id)
        ->and($b->riwayatKondisi()->count())->toBe(0);
    $spy->shouldHaveReceived('handle')->withArgs(fn ($aset, $sumber, $sumberId) => $aset->is($a) && $sumber === 'peminjaman' && $sumberId === $p->id)->once();
    $spy->shouldNotHaveReceived('handle', [Mockery::on(fn ($aset) => $aset->is($b)), Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any()]);
});

it('rusak berat saat kembali memperbarui kondisi dan membuka tiket; membaik tidak membuka tiket', function () {
    $r = pj4Ruang();
    $pic = pj4Pic($r);
    $rusak = Aset::factory()->diRuangan($r)->dapatDipinjam()->create();
    $membaik = Aset::factory()->diRuangan($r)->dapatDipinjam()->create(['kondisi' => 'RR']);
    $p = Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->untuk($rusak, $membaik)->create();
    $spy = Mockery::spy(BukaTiketPemeliharaan::class);
    app()->instance(BukaTiketPemeliharaan::class, $spy);

    app(KembalikanPeminjaman::class)->handle($p, [$rusak->id => 'RB', $membaik->id => 'B'], $pic);

    expect($rusak->fresh()->kondisi)->toBe(KondisiAset::RusakBerat)->and($membaik->fresh()->kondisi)->toBe(KondisiAset::Baik);
    $spy->shouldHaveReceived('handle')->once();
});

it('aset yang dikembalikan menjadi tersedia lagi (rusak berat tidak)', function () {
    $r = pj4Ruang();
    [$p, $a, $b] = pj4Dipinjam($r);
    $cek = app(CekKetersediaan::class);

    expect($cek->tersedia($a, now()->addHour(), now()->addHours(3)))->toBeFalse();

    app(KembalikanPeminjaman::class)->handle($p, [$a->id => 'B', $b->id => 'RB'], pj4Pic($r));

    expect($cek->tersedia($a->fresh(), now()->addHour(), now()->addHours(3)))->toBeTrue()
        ->and($cek->tersedia($b->fresh(), now()->addHour(), now()->addHours(3)))->toBeFalse();
});

it('hanya PIC ruangan asal atau admin yang menerima pengembalian; peminjam sendiri tidak', function () {
    [$r1, $r2] = [pj4Ruang('R-1'), pj4Ruang('R-2')];
    [$p, $a, $b] = pj4Dipinjam($r1);
    $kondisi = [$a->id => 'B', $b->id => 'B'];
    $peminjam = User::factory()->create();
    $peminjam->assignRole('civitas');
    $p->update(['peminjam_user_id' => $peminjam->id]);

    expect(fn () => app(KembalikanPeminjaman::class)->handle($p, $kondisi, pj4Pic($r2)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(KembalikanPeminjaman::class)->handle($p, $kondisi, $peminjam))->toThrow(AuthorizationException::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin-bmn');
    expect(app(KembalikanPeminjaman::class)->handle($p, $kondisi, $admin)->status)->toBe(StatusPeminjaman::Dikembalikan);
});

it('hanya peminjaman berstatus dipinjam yang dapat dikembalikan, dan tidak dua kali', function (StatusPeminjaman $status) {
    $r = pj4Ruang();
    $aset = Aset::factory()->diRuangan($r)->dapatDipinjam()->create();
    $p = Peminjaman::factory()->status($status)->untuk($aset)->create();

    expect(fn () => app(KembalikanPeminjaman::class)->handle($p, [$aset->id => 'B'], pj4Pic($r)))->toThrow(ValidationException::class, 'tidak dapat dikembalikan');
})->with([StatusPeminjaman::Diajukan, StatusPeminjaman::Disetujui, StatusPeminjaman::Dikembalikan, StatusPeminjaman::Ditolak, StatusPeminjaman::Dibatalkan]);

it('pengembalian ganda ditolak setelah yang pertama berhasil', function () {
    $r = pj4Ruang();
    $pic = pj4Pic($r);
    [$p, $a, $b] = pj4Dipinjam($r);
    $k = [$a->id => 'B', $b->id => 'B'];
    $salinan = Peminjaman::query()->find($p->id);

    app(KembalikanPeminjaman::class)->handle($p, $k, $pic);

    expect(fn () => app(KembalikanPeminjaman::class)->handle($salinan, $k, $pic))->toThrow(ValidationException::class);
});

it('keterlambatan dihitung dari rencana kembali, bukan status', function () {
    $r = pj4Ruang();
    $tepat = Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->rentang(now()->subHour()->toDateTimeString(), now()->addHour()->toDateTimeString())->create();
    $telat = Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->rentang(now()->subDays(3)->toDateTimeString(), now()->subDay()->toDateTimeString())->create();
    $kembali = Peminjaman::factory()->status(StatusPeminjaman::Dikembalikan)->rentang(now()->subDays(3)->toDateTimeString(), now()->subDay()->toDateTimeString())->create();

    expect($tepat->terlambat())->toBeFalse()->and($telat->terlambat())->toBeTrue()->and($kembali->terlambat())->toBeFalse();
});

it('daftar peminjaman punya tab Aktif, Terlambat, Diajukan, Riwayat dengan isi yang benar', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin-bmn');
    $admin->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');
    $this->actingAs($admin);

    $aktif = Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->rentang(now()->subHour()->toDateTimeString(), now()->addDay()->toDateTimeString())->create();
    $disetujui = Peminjaman::factory()->status(StatusPeminjaman::Disetujui)->create();
    $telat = Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->rentang(now()->subDays(4)->toDateTimeString(), now()->subDays(2)->toDateTimeString())->create();
    $diajukan = Peminjaman::factory()->status(StatusPeminjaman::Diajukan)->create();
    $selesai = Peminjaman::factory()->status(StatusPeminjaman::Dikembalikan)->create();
    $ditolak = Peminjaman::factory()->status(StatusPeminjaman::Ditolak)->create();

    Livewire::test(DaftarPeminjaman::class)
        ->assertCanSeeTableRecords([$aktif, $disetujui, $telat])->assertCanNotSeeTableRecords([$diajukan, $selesai, $ditolak])
        ->set('activeTab', 'terlambat')->assertCanSeeTableRecords([$telat])->assertCanNotSeeTableRecords([$aktif, $disetujui, $diajukan])
        ->set('activeTab', 'diajukan')->assertCanSeeTableRecords([$diajukan])->assertCanNotSeeTableRecords([$aktif, $telat])
        ->set('activeTab', 'riwayat')->assertCanSeeTableRecords([$selesai, $ditolak])->assertCanNotSeeTableRecords([$aktif, $diajukan]);
});

it('halaman peminjaman: PIC menerima pengembalian lewat aksi dengan kondisi per barang', function () {
    $r = pj4Ruang();
    $pic = pj4Pic($r);
    [$p, $a, $b] = pj4Dipinjam($r);
    $this->actingAs($pic);

    $komponen = Livewire::test(LihatPeminjaman::class, ['record' => $p->getRouteKey()])
        ->assertActionVisible('kembalikan');

    $komponen->callAction('kembalikan', ['barang' => [
        ['aset_id' => $a->id, 'kondisi' => 'RR', 'catatan' => 'Retak'],
        ['aset_id' => $b->id, 'kondisi' => 'B', 'catatan' => null],
    ]]);

    expect($p->fresh()->status)->toBe(StatusPeminjaman::Dikembalikan)
        ->and($a->fresh()->kondisi)->toBe(KondisiAset::RusakRingan)
        ->and($p->item()->where('aset_id', $a->id)->first()->catatan)->toBe('Retak');

    Livewire::test(LihatPeminjaman::class, ['record' => $p->getRouteKey()])->assertActionHidden('kembalikan');
});
