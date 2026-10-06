<?php

use App\Actions\Peminjaman\AjukanPeminjaman;
use App\Actions\Peminjaman\BatalkanPeminjaman;
use App\Actions\Peminjaman\CatatPermohonanPihakLuar;
use App\Actions\Peminjaman\SerahkanPeminjaman;
use App\Actions\Peminjaman\SetujuiPeminjaman;
use App\Actions\Peminjaman\TolakPeminjaman;
use App\Enums\JenisPeminjam;
use App\Enums\KondisiAset;
use App\Enums\StatusPeminjaman;
use App\Exceptions\FiturNonaktif;
use App\Filament\Resources\Peminjaman\Pages\DaftarPeminjaman;
use App\Filament\Resources\Peminjaman\Pages\LihatPeminjaman;
use App\Livewire\AjukanPinjam;
use App\Livewire\PinjamanSaya;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\Pengaturan;
use Carbon\CarbonInterface;
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
    config(['aset.lock_tunggu_detik' => 0]);
});

function pj3Pelaku(string $peran, array $ruangan = []): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $user;
}

function pj3Ruang(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

function pj3Barang(Ruangan $r, array $atribut = []): Aset
{
    return Aset::factory()->diRuangan($r)->dapatDipinjam()->create($atribut);
}

function pj3Ajukan(array $ids, User $pemohon, ?CarbonInterface $mulai = null, ?CarbonInterface $selesai = null, string $keperluan = 'Mengajar')
{
    $mulai ??= now()->addDay()->setTime(8, 0);

    return app(AjukanPeminjaman::class)->handle($ids, $keperluan, $mulai, $selesai ?? $mulai->copy()->setTime(16, 0), $pemohon);
}

it('civitas mengajukan peminjaman online: status diajukan, data pemohon dari akun (US-PJM-02)', function () {
    $r = pj3Ruang('R-1');
    $civitas = pj3Pelaku('civitas');
    $civitas->update(['name' => 'Dr. Rani', 'no_hp' => '0812']);
    $aset = pj3Barang($r);

    $p = pj3Ajukan([$aset->id], $civitas);

    expect($p->status)->toBe(StatusPeminjaman::Diajukan)
        ->and($p->nomor)->toBe('PJM-'.now()->year.'-00001')
        ->and($p)->peminjam_user_id->toBe($civitas->id)->nama_peminjam->toBe('Dr. Rani')->kontak_peminjam->toBe('0812')
        ->and($p->jenis_peminjam)->toBe(JenisPeminjam::Civitas)
        ->and($p->dicatat_oleh)->toBe($civitas->id)
        ->and($p->item)->toHaveCount(1)
        ->and($aset->fresh()->sedangDipinjam)->toBeFalse();
});

it('menolak pengajuan bila rentang bentrok dengan peminjaman disetujui (US-PJM-02)', function () {
    $r = pj3Ruang('R-1');
    $aset = pj3Barang($r);
    $mulai = now()->addDay()->setTime(8, 0);
    Peminjaman::factory()->status(StatusPeminjaman::Disetujui)->rentang($mulai->toDateTimeString(), $mulai->copy()->setTime(12, 0)->toDateTimeString())->untuk($aset)->create();

    expect(fn () => pj3Ajukan([$aset->id], pj3Pelaku('civitas'), $mulai->copy()->setTime(10, 0), $mulai->copy()->setTime(14, 0)))
        ->toThrow(ValidationException::class, 'tidak tersedia');
    // bersinggungan di batas → boleh
    expect(pj3Ajukan([$aset->id], pj3Pelaku('civitas'), $mulai->copy()->setTime(12, 0), $mulai->copy()->setTime(15, 0))->status)->toBe(StatusPeminjaman::Diajukan);
});

it('hanya peran dengan peminjaman.ajukan (civitas) yang dapat mengajukan online', function () {
    $aset = pj3Barang(pj3Ruang('R-1'));

    foreach (['pic-ruangan', 'admin-bmn', 'pimpinan', 'pejabat-penatausahaan'] as $peran) {
        expect(fn () => pj3Ajukan([$aset->id], pj3Pelaku($peran)))->toThrow(AuthorizationException::class);
    }
});

it('memvalidasi masa lalu, durasi maksimal, keperluan, aset tak dapat dipinjam, dan lintas ruangan', function () {
    [$r1, $r2] = [pj3Ruang('R-1'), pj3Ruang('R-2')];
    $civitas = pj3Pelaku('civitas');
    $a = pj3Barang($r1);

    expect(fn () => pj3Ajukan([$a->id], $civitas, now()->subDay()))->toThrow(ValidationException::class)
        ->and(fn () => pj3Ajukan([$a->id], $civitas, now()->addDay(), now()->addDays(20)))->toThrow(ValidationException::class, 'melebihi batas')
        ->and(fn () => pj3Ajukan([$a->id], $civitas, keperluan: ' '))->toThrow(ValidationException::class)
        ->and(fn () => pj3Ajukan([], $civitas))->toThrow(ValidationException::class)
        ->and(fn () => pj3Ajukan([pj3Barang($r1, ['kondisi' => 'RB'])->id], $civitas))->toThrow(ValidationException::class)
        ->and(fn () => pj3Ajukan([Aset::factory()->diRuangan($r1)->create()->id], $civitas))->toThrow(ValidationException::class, 'tidak ditandai dapat dipinjam')
        ->and(fn () => pj3Ajukan([$a->id, pj3Barang($r2)->id], $civitas))->toThrow(ValidationException::class, 'satu ruangan');
    expect(Peminjaman::count())->toBe(0);
});

it('toggle fitur peminjaman menolak pengajuan (BR-22)', function () {
    $aset = pj3Barang(pj3Ruang('R-1'));
    Pengaturan::simpan('fitur_peminjaman', false);

    expect(fn () => pj3Ajukan([$aset->id], pj3Pelaku('civitas')))->toThrow(FiturNonaktif::class);
});

it('PIC ruangan asal menyetujui; PIC ruangan lain dan pejabat tidak (BR-05, BR-09)', function () {
    [$r1, $r2] = [pj3Ruang('R-1'), pj3Ruang('R-2')];
    $p = pj3Ajukan([pj3Barang($r1)->id], pj3Pelaku('civitas'));

    expect(fn () => app(SetujuiPeminjaman::class)->handle($p, pj3Pelaku('pic-ruangan', [$r2])))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetujuiPeminjaman::class)->handle($p, pj3Pelaku('pejabat-penatausahaan')))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetujuiPeminjaman::class)->handle($p, pj3Pelaku('civitas')))->toThrow(AuthorizationException::class);

    $pic = pj3Pelaku('pic-ruangan', [$r1]);
    $hasil = app(SetujuiPeminjaman::class)->handle($p, $pic, 'Silakan diambil');

    expect($hasil)->status->toBe(StatusPeminjaman::Disetujui)->diputuskan_oleh->toBe($pic->id)->catatan_keputusan->toBe('Silakan diambil')
        ->and($hasil->diputuskan_pada)->not->toBeNull();
});

it('admin dapat menyetujui peminjaman ruangan mana pun', function () {
    $p = pj3Ajukan([pj3Barang(pj3Ruang('R-1'))->id], pj3Pelaku('civitas'));

    expect(app(SetujuiPeminjaman::class)->handle($p, pj3Pelaku('admin-bmn'))->status)->toBe(StatusPeminjaman::Disetujui);
});

it('mengecek ulang ketersediaan saat persetujuan: bentrok dengan yang lebih dulu disetujui (BR-08)', function () {
    $r = pj3Ruang('R-1');
    $pic = pj3Pelaku('pic-ruangan', [$r]);
    $aset = pj3Barang($r);
    $mulai = now()->addDay()->setTime(8, 0);

    $pertama = pj3Ajukan([$aset->id], pj3Pelaku('civitas'), $mulai, $mulai->copy()->setTime(12, 0));
    $kedua = pj3Ajukan([$aset->id], pj3Pelaku('civitas'), $mulai->copy()->setTime(10, 0), $mulai->copy()->setTime(14, 0)); // sama-sama diajukan: belum menahan

    app(SetujuiPeminjaman::class)->handle($pertama, $pic);

    expect(fn () => app(SetujuiPeminjaman::class)->handle($kedua, $pic))->toThrow(ValidationException::class, 'bentrok dengan '.$pertama->nomor);
    expect($kedua->fresh()->status)->toBe(StatusPeminjaman::Diajukan);

    // setelah ditolak tidak bisa disetujui lagi; dan persetujuan yang sama dua kali ditolak
    expect(fn () => app(SetujuiPeminjaman::class)->handle($pertama, $pic))->toThrow(ValidationException::class, 'tidak dapat disetujui');
});

it('persetujuan gagal bila aset menjadi tidak layak setelah diajukan', function () {
    $r = pj3Ruang('R-1');
    $aset = pj3Barang($r);
    $p = pj3Ajukan([$aset->id], pj3Pelaku('civitas'));

    $aset->update(['status' => 'dalam_perbaikan']);

    expect(fn () => app(SetujuiPeminjaman::class)->handle($p, pj3Pelaku('pic-ruangan', [$r])))->toThrow(ValidationException::class);
    expect($p->fresh()->status)->toBe(StatusPeminjaman::Diajukan);
});

it('menolak pengajuan dengan alasan wajib', function () {
    $r = pj3Ruang('R-1');
    $pic = pj3Pelaku('pic-ruangan', [$r]);
    $p = pj3Ajukan([pj3Barang($r)->id], pj3Pelaku('civitas'));

    expect(fn () => app(TolakPeminjaman::class)->handle($p, $pic, ''))->toThrow(ValidationException::class);

    $hasil = app(TolakPeminjaman::class)->handle($p, $pic, 'Dipakai kegiatan lain');
    expect($hasil)->status->toBe(StatusPeminjaman::Ditolak)->catatan_keputusan->toBe('Dipakai kegiatan lain');
    expect(fn () => app(TolakPeminjaman::class)->handle($p, $pic, 'lagi'))->toThrow(ValidationException::class);
});

it('serah terima: disetujui → dipinjam dengan kondisi saat pinjam diperbarui', function () {
    $r = pj3Ruang('R-1');
    $pic = pj3Pelaku('pic-ruangan', [$r]);
    $aset = pj3Barang($r);
    $p = pj3Ajukan([$aset->id], pj3Pelaku('civitas'));

    expect(fn () => app(SerahkanPeminjaman::class)->handle($p, $pic))->toThrow(ValidationException::class); // belum disetujui

    app(SetujuiPeminjaman::class)->handle($p, $pic);
    $aset->update(['kondisi' => KondisiAset::RusakRingan]);

    $hasil = app(SerahkanPeminjaman::class)->handle($p, $pic);

    expect($hasil)->status->toBe(StatusPeminjaman::Dipinjam)->diserahkan_oleh->toBe($pic->id)
        ->and($hasil->item()->first()->kondisi_saat_pinjam)->toBe(KondisiAset::RusakRingan)
        ->and($aset->fresh()->sedangDipinjam)->toBeTrue();
});

it('serah terima ditolak bila aset menjadi rusak berat setelah disetujui', function () {
    $r = pj3Ruang('R-1');
    $pic = pj3Pelaku('pic-ruangan', [$r]);
    $aset = pj3Barang($r);
    $p = pj3Ajukan([$aset->id], pj3Pelaku('civitas'));
    app(SetujuiPeminjaman::class)->handle($p, $pic);

    $aset->update(['kondisi' => 'RB']);

    expect(fn () => app(SerahkanPeminjaman::class)->handle($p, $pic))->toThrow(ValidationException::class);
    expect($p->fresh()->status)->toBe(StatusPeminjaman::Disetujui);
});

it('peminjam membatalkan pengajuan atau yang disetujui, tetapi tidak yang sudah dipinjam', function () {
    $r = pj3Ruang('R-1');
    $pic = pj3Pelaku('pic-ruangan', [$r]);
    $civitas = pj3Pelaku('civitas');
    $a = pj3Ajukan([pj3Barang($r)->id], $civitas);
    $b = pj3Ajukan([pj3Barang($r)->id], $civitas);
    app(SetujuiPeminjaman::class)->handle($b, $pic);
    $c = pj3Ajukan([pj3Barang($r)->id], $civitas);
    app(SetujuiPeminjaman::class)->handle($c, $pic);
    app(SerahkanPeminjaman::class)->handle($c, $pic);

    expect(app(BatalkanPeminjaman::class)->handle($a, $civitas)->status)->toBe(StatusPeminjaman::Dibatalkan)
        ->and(app(BatalkanPeminjaman::class)->handle($b, $civitas)->status)->toBe(StatusPeminjaman::Dibatalkan)
        ->and(fn () => app(BatalkanPeminjaman::class)->handle($c, $civitas))->toThrow(ValidationException::class, 'dibatalkan')
        ->and(fn () => app(BatalkanPeminjaman::class)->handle(pj3Ajukan([pj3Barang($r)->id], $civitas), pj3Pelaku('civitas')))->toThrow(AuthorizationException::class);
});

it('pembatalan membebaskan aset untuk peminjam lain', function () {
    $r = pj3Ruang('R-1');
    $aset = pj3Barang($r);
    $pic = pj3Pelaku('pic-ruangan', [$r]);
    $civitas = pj3Pelaku('civitas');
    $mulai = now()->addDay()->setTime(8, 0);
    $p = pj3Ajukan([$aset->id], $civitas, $mulai, $mulai->copy()->addHours(4));
    app(SetujuiPeminjaman::class)->handle($p, $pic);

    expect(fn () => pj3Ajukan([$aset->id], pj3Pelaku('civitas'), $mulai, $mulai->copy()->addHours(2)))->toThrow(ValidationException::class);

    app(BatalkanPeminjaman::class)->handle($p, $civitas);
    expect(pj3Ajukan([$aset->id], pj3Pelaku('civitas'), $mulai, $mulai->copy()->addHours(2))->status)->toBe(StatusPeminjaman::Diajukan);
});

it('permohonan pihak luar dicatat admin, diputuskan pejabat, tidak diserahkan lewat sistem (BR-07)', function () {
    $r = pj3Ruang('R-1');
    $aset = pj3Barang($r);
    $admin = pj3Pelaku('admin-bmn');
    $pejabat = pj3Pelaku('pejabat-penatausahaan');
    $mulai = now()->addDays(3)->setTime(8, 0);

    expect(fn () => app(CatatPermohonanPihakLuar::class)->handle([$aset->id], ['nama_peminjam' => 'PT Maju'], 'Acara', $mulai, $mulai->copy()->addHours(5), pj3Pelaku('pic-ruangan', [$r])))
        ->toThrow(AuthorizationException::class);

    $p = app(CatatPermohonanPihakLuar::class)->handle([$aset->id], ['nama_peminjam' => 'PT Maju', 'kontak_peminjam' => '021', 'unit_peminjam' => 'Instansi X'], 'Acara', $mulai, $mulai->copy()->addHours(5), $admin);

    expect($p)->jenis_peminjam->toBe(JenisPeminjam::PihakLuar)->status->toBe(StatusPeminjaman::Diajukan)->peminjam_user_id->toBeNull()
        ->nama_peminjam->toBe('PT Maju')->dicatat_oleh->toBe($admin->id);

    // PIC ruangan tidak memutuskan pihak luar; pejabat memutuskan
    expect(fn () => app(SetujuiPeminjaman::class)->handle($p, pj3Pelaku('pic-ruangan', [$r])))->toThrow(AuthorizationException::class);
    app(SetujuiPeminjaman::class)->handle($p, $pejabat);
    expect($p->fresh()->status)->toBe(StatusPeminjaman::Disetujui);

    expect(fn () => app(SerahkanPeminjaman::class)->handle($p, $pejabat))->toThrow(ValidationException::class, 'pihak luar');
    expect(fn () => app(CatatPermohonanPihakLuar::class)->handle([$aset->id], ['nama_peminjam' => ''], 'x', $mulai, $mulai->copy()->addHour(), $admin))->toThrow(ValidationException::class);
});

it('persetujuan pihak luar yang disetujui menahan aset terhadap pengajuan civitas', function () {
    $r = pj3Ruang('R-1');
    $aset = pj3Barang($r);
    $mulai = now()->addDays(2)->setTime(8, 0);
    $p = app(CatatPermohonanPihakLuar::class)->handle([$aset->id], ['nama_peminjam' => 'PT Maju'], 'Acara', $mulai, $mulai->copy()->addHours(6), pj3Pelaku('admin-bmn'));
    app(SetujuiPeminjaman::class)->handle($p, pj3Pelaku('pejabat-penatausahaan'));

    expect(fn () => pj3Ajukan([$aset->id], pj3Pelaku('civitas'), $mulai->copy()->addHour(), $mulai->copy()->addHours(3)))->toThrow(ValidationException::class);
});

it('visibilitas peminjaman: PIC hanya ruangannya, pejabat hanya pihak luar, admin semua (BR-23)', function () {
    [$r1, $r2] = [pj3Ruang('R-1'), pj3Ruang('R-2')];
    $pic1 = pj3Pelaku('pic-ruangan', [$r1]);
    $pejabat = pj3Pelaku('pejabat-penatausahaan');
    $civitas = pj3Pelaku('civitas');
    $di1 = pj3Ajukan([pj3Barang($r1)->id], $civitas);
    $di2 = pj3Ajukan([pj3Barang($r2)->id], $civitas);
    $luar = app(CatatPermohonanPihakLuar::class)->handle([pj3Barang($r2)->id], ['nama_peminjam' => 'PT X'], 'x', now()->addDays(2), now()->addDays(2)->addHours(3), pj3Pelaku('admin-bmn'));

    $ids = fn (User $u) => Peminjaman::query()->terlihatOleh($u)->pluck('id')->sort()->values()->all();

    expect($ids($pic1))->toBe([$di1->id])
        ->and($ids($pejabat))->toBe([$luar->id])
        ->and($ids(pj3Pelaku('admin-bmn')))->toEqualCanonicalizing([$di1->id, $di2->id, $luar->id])
        ->and($ids($civitas))->toEqualCanonicalizing([$di1->id, $di2->id])
        ->and($ids(pj3Pelaku('civitas')))->toBe([]);
});

it('data pribadi peminjam hanya untuk yang berhak (BR-23)', function () {
    [$r1, $r2] = [pj3Ruang('R-1'), pj3Ruang('R-2')];
    $civitas = pj3Pelaku('civitas');
    $p = pj3Ajukan([pj3Barang($r1)->id], $civitas);

    expect(pj3Pelaku('pic-ruangan', [$r1])->can('lihatDataPribadi', $p))->toBeTrue()
        ->and(pj3Pelaku('pic-ruangan', [$r2])->can('lihatDataPribadi', $p))->toBeFalse()
        ->and(pj3Pelaku('admin-bmn')->can('lihatDataPribadi', $p))->toBeTrue()
        ->and($civitas->can('lihatDataPribadi', $p))->toBeTrue()
        ->and(pj3Pelaku('civitas')->can('lihatDataPribadi', $p))->toBeFalse()
        ->and(pj3Pelaku('pimpinan')->can('lihatDataPribadi', $p))->toBeFalse();
});

it('panel: PIC melihat daftar miliknya, membuka, menyetujui, dan menyerahkan', function () {
    [$r1, $r2] = [pj3Ruang('R-1'), pj3Ruang('R-2')];
    $pic = pj3Pelaku('pic-ruangan', [$r1]);
    $milik = pj3Ajukan([pj3Barang($r1)->id], pj3Pelaku('civitas'));
    $asing = pj3Ajukan([pj3Barang($r2)->id], pj3Pelaku('civitas'));
    $this->actingAs($pic);

    Livewire::test(DaftarPeminjaman::class)->assertCanSeeTableRecords([$milik])->assertCanNotSeeTableRecords([$asing]);
    $this->get('/admin/peminjaman/'.$asing->id)->assertNotFound();

    Livewire::test(LihatPeminjaman::class, ['record' => $milik->getRouteKey()])
        ->assertActionVisible('setujui')->assertActionVisible('tolak')->assertActionHidden('serahkan')
        ->callAction('setujui', ['catatan' => 'ok']);
    expect($milik->fresh()->status)->toBe(StatusPeminjaman::Disetujui);

    Livewire::test(LihatPeminjaman::class, ['record' => $milik->getRouteKey()])
        ->assertActionHidden('setujui')->assertActionVisible('serahkan')->assertActionVisible('batalkan')
        ->callAction('serahkan');
    expect($milik->fresh()->status)->toBe(StatusPeminjaman::Dipinjam);
});

it('panel: admin mencatat permohonan pihak luar; pejabat melihat dan memutuskannya', function () {
    $r = pj3Ruang('R-1');
    $aset = pj3Barang($r);
    $this->actingAs(pj3Pelaku('admin-bmn'));

    Livewire::test(DaftarPeminjaman::class)
        ->callAction('catatPihakLuar', [
            'nama_peminjam' => 'Instansi Z', 'kontak_peminjam' => '022', 'aset' => [$aset->id],
            'mulai' => now()->addDays(2)->setTime(8, 0)->toDateTimeString(), 'rencana_kembali' => now()->addDays(2)->setTime(12, 0)->toDateTimeString(), 'keperluan' => 'Pelatihan',
        ])->assertHasNoActionErrors();

    $p = Peminjaman::query()->firstOrFail();
    expect($p->jenis_peminjam)->toBe(JenisPeminjam::PihakLuar);

    $this->actingAs(pj3Pelaku('pejabat-penatausahaan'));
    Livewire::test(DaftarPeminjaman::class)->assertCanSeeTableRecords([$p])->assertActionHidden('catatPihakLuar');
    Livewire::test(LihatPeminjaman::class, ['record' => $p->getRouteKey()])
        ->assertActionVisible('setujui')->assertActionHidden('serahkan')
        ->callAction('tolak', ['catatan' => 'Tidak sesuai kebijakan']);
    expect($p->fresh()->status)->toBe(StatusPeminjaman::Ditolak);
});

it('/pinjam: hanya civitas; katalog memuat field putih dan menandai barang yang tidak tersedia', function () {
    $r = pj3Ruang('R-1');
    $r->update(['dapat_dipinjam' => true, 'kapasitas' => 30]);
    $tersedia = pj3Barang($r, ['nama' => 'Proyektor A', 'merk_tipe' => 'Epson', 'nilai_perolehan' => '12345678.00']);
    $dipakai = pj3Barang($r, ['nama' => 'Proyektor B']);
    pj3Barang($r, ['nama' => 'Proyektor Rusak', 'kondisi' => 'RB']);
    Aset::factory()->diRuangan($r)->create(['nama' => 'Bukan Katalog']);
    $mulai = now()->addDay()->setTime(8, 0);
    Peminjaman::factory()->status(StatusPeminjaman::Disetujui)->rentang($mulai->toDateTimeString(), $mulai->copy()->setTime(16, 0)->toDateTimeString())->untuk($dipakai)->create();

    $this->get('/pinjam')->assertRedirect('/login');
    $this->actingAs(pj3Pelaku('pic-ruangan', [$r]))->get('/pinjam')->assertForbidden();

    $this->actingAs(pj3Pelaku('civitas'));
    $this->get('/pinjam')->assertOk()->assertSee('Pinjam barang');

    Livewire::test(AjukanPinjam::class)
        ->assertSee('Proyektor A')->assertSee('Epson')->assertSee('Proyektor B')->assertSee('Tidak tersedia pada rentang ini')
        ->assertDontSee('Proyektor Rusak')->assertDontSee('Bukan Katalog')->assertDontSee('12345678')
        ->assertSee('Ruang R-1')->assertSee('kapasitas 30');
});

it('/pinjam: civitas mengajukan lalu melihat di Pinjaman saya dan dapat membatalkan', function () {
    $r = pj3Ruang('R-1');
    $aset = pj3Barang($r, ['nama' => 'Laptop Pinjam']);
    $civitas = pj3Pelaku('civitas');
    $this->actingAs($civitas);

    Livewire::test(AjukanPinjam::class)
        ->set('dipilih', [$aset->id])->set('keperluan', 'Workshop')
        ->call('ajukan')
        ->assertHasNoErrors()->assertSee('PJM-'.now()->year.'-00001');

    $p = Peminjaman::first();
    expect($p->peminjam_user_id)->toBe($civitas->id);

    Livewire::test(PinjamanSaya::class)->assertSee('PJM-'.now()->year.'-00001')->assertSee('Laptop Pinjam')->assertSee('Diajukan')
        ->call('batalkan', $p->id)->assertSee('Dibatalkan');
    expect($p->fresh()->status)->toBe(StatusPeminjaman::Dibatalkan);
});

it('/pinjam: pengajuan tanpa pilihan atau bentrok menampilkan galat', function () {
    $r = pj3Ruang('R-1');
    $aset = pj3Barang($r);
    $mulai = now()->addDay()->setTime(8, 0);
    Peminjaman::factory()->status(StatusPeminjaman::Disetujui)->rentang($mulai->toDateTimeString(), $mulai->copy()->setTime(16, 0)->toDateTimeString())->untuk($aset)->create();
    $this->actingAs(pj3Pelaku('civitas'));

    Livewire::test(AjukanPinjam::class)->set('keperluan', 'X')->call('ajukan')->assertHasErrors('dipilih');
    Livewire::test(AjukanPinjam::class)->set('dipilih', [$aset->id])->set('keperluan', 'X')->call('ajukan')->assertHasErrors('aset');
});

it('/pinjaman-saya hanya menampilkan milik sendiri dan menolak membatalkan milik orang lain', function () {
    $r = pj3Ruang('R-1');
    $saya = pj3Pelaku('civitas');
    $lain = pj3Pelaku('civitas');
    $milik = pj3Ajukan([pj3Barang($r, ['nama' => 'Barang Saya'])->id], $saya);
    $orang = pj3Ajukan([pj3Barang($r, ['nama' => 'Barang Orang'])->id], $lain);
    $this->actingAs($saya);

    Livewire::test(PinjamanSaya::class)->assertSee('Barang Saya')->assertDontSee('Barang Orang');
    expect(fn () => Livewire::test(PinjamanSaya::class)->call('batalkan', $orang->id)->assertStatus(404))->not->toThrow(Throwable::class);
    expect($orang->fresh()->status)->toBe(StatusPeminjaman::Diajukan);
});
