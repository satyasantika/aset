<?php

use App\Actions\Dbr\BangkitkanDbr;
use App\Actions\Dbr\KembalikanDbr;
use App\Actions\Dbr\SahkanDbr;
use App\Actions\Dbr\SetujuiDbrOlehPic;
use App\Actions\Mutasi\AjukanMutasi;
use App\Actions\Mutasi\SetujuiMutasi;
use App\Enums\KondisiAset;
use App\Enums\StatusDbr;
use App\Filament\Resources\Dbr\Pages\DaftarDbr;
use App\Filament\Resources\Dbr\Pages\LihatDbr;
use App\Models\Aset;
use App\Models\DbrVersi;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\Pengaturan;
use Barryvdh\DomPDF\Facade\Pdf;
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

function dbrUser(string $peran, array $ruangan = [], array $atribut = []): User
{
    $u = User::factory()->create($atribut);
    $u->assignRole($peran);
    $u->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));
    $u->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $u;
}

function dbrRuang(string $kode = 'R-1'): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

/** @return array{0: Ruangan, 1: User, 2: User} ruangan, PIC, pejabat */
function dbrSkenario(): array
{
    $r = dbrRuang();

    return [$r, dbrUser('pic-ruangan', [$r], ['name' => 'Bu PIC', 'nip' => '198001012005012001']), dbrUser('pejabat-penatausahaan', [], ['name' => 'Pak Pejabat', 'nip' => '197001011995031001'])];
}

function dbrDisahkan(Ruangan $r, User $pic, User $pejabat): DbrVersi
{
    $dbr = app(BangkitkanDbr::class)->handle($r, $pic);
    app(SetujuiDbrOlehPic::class)->handle($dbr, $pic);

    return app(SahkanDbr::class)->handle($dbr, $pejabat);
}

it('membangkitkan DBR draf dengan snapshot lengkap dari data aset (tanpa hilang/dihapus)', function () {
    [$r, $pic] = dbrSkenario();
    Pengaturan::simpan('instansi_baris2', 'UNIVERSITAS SILIWANGI');
    $a = Aset::factory()->diRuangan($r)->create(['nama' => 'Meja', 'kode_barang' => '3100102001', 'nup' => 1, 'kondisi' => 'RR', 'tahun_perolehan' => 2020, 'keterangan' => 'Ada goresan']);
    Aset::factory()->diRuangan($r)->create(['nama' => 'Perbaikan', 'status' => 'dalam_perbaikan', 'kode_barang' => '3100102001', 'nup' => 2]);
    Aset::factory()->diRuangan($r)->create(['nama' => 'Hilang', 'status' => 'hilang']);
    Aset::factory()->diRuangan($r)->create(['nama' => 'Dihapus', 'status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK', 'tanggal_sk_penghapusan' => '2026-01-01']);
    Aset::factory()->create(['nama' => 'Ruang Lain']);

    $dbr = app(BangkitkanDbr::class)->handle($r, $pic);

    $s = $dbr->snapshot;
    expect($dbr)->status->toBe(StatusDbr::Draf)->versi->toBe(1)->jenis->toBe('dbr')->ruangan_id->toBe($r->id)
        ->and($s['ruangan'])->toMatchArray(['kode' => 'R-1', 'nama' => 'Ruang R-1'])
        ->and(collect($s['aset'])->pluck('nama')->sort()->values()->all())->toBe(['Meja', 'Perbaikan'])
        ->and($s['ringkasan'])->toMatchArray(['jumlah' => 2, 'B' => 1, 'RR' => 1, 'RB' => 0])
        ->and($s['kop']['instansi_baris2'])->toBe('UNIVERSITAS SILIWANGI')
        ->and($s['hash_aset'])->toHaveLength(64)
        ->and($s['dibangkitkan_oleh']['nama'])->toBe($pic->name)
        ->and($s['penandatangan'])->toBe(['pic' => null, 'pejabat' => null]);
    $meja = collect($s['aset'])->firstWhere('nama', 'Meja');
    expect($meja)->toMatchArray(['kode_barang' => '3100102001', 'nup' => 1, 'kondisi' => 'RR', 'tahun_perolehan' => 2020, 'keterangan' => 'Ada goresan', 'id' => $a->id]);
});

it('membangkitkan ulang saat masih draf memperbarui snapshot, bukan membuat versi baru', function () {
    [$r, $pic] = dbrSkenario();
    Aset::factory()->diRuangan($r)->create(['nama' => 'Satu']);
    $v1 = app(BangkitkanDbr::class)->handle($r, $pic);
    Aset::factory()->diRuangan($r)->create(['nama' => 'Dua']);

    $lagi = app(BangkitkanDbr::class)->handle($r, $pic);

    expect($lagi->id)->toBe($v1->id)->and(DbrVersi::count())->toBe(1)->and($lagi->snapshot['ringkasan']['jumlah'])->toBe(2);
});

it('DBL memuat aset berlokasi lainnya dan hanya dapat dibangkitkan admin', function () {
    [$r, $pic] = dbrSkenario();
    Aset::factory()->create(['ruangan_id' => null, 'lokasi_lainnya' => 'Selasar Timur', 'nama' => 'Bangku Taman']);
    Aset::factory()->diRuangan($r)->create(['nama' => 'Bukan DBL']);
    $admin = dbrUser('admin-bmn');

    expect(fn () => app(BangkitkanDbr::class)->handle(null, $pic))->toThrow(AuthorizationException::class);

    $dbl = app(BangkitkanDbr::class)->handle(null, $admin);
    expect($dbl)->jenis->toBe('dbl')->ruangan_id->toBeNull()->versi->toBe(1)
        ->and(collect($dbl->snapshot['aset'])->pluck('nama')->all())->toBe(['Bangku Taman'])
        ->and($dbl->snapshot['ruangan'])->toBeNull();
});

it('PIC hanya membangkitkan DBR ruangannya; peran lain ditolak', function () {
    [$r1, $pic] = dbrSkenario();
    $r2 = dbrRuang('R-2');

    expect(app(BangkitkanDbr::class)->handle($r1, $pic))->toBeInstanceOf(DbrVersi::class)
        ->and(fn () => app(BangkitkanDbr::class)->handle($r2, $pic))->toThrow(AuthorizationException::class);

    foreach (['pimpinan', 'pejabat-penatausahaan', 'civitas'] as $peran) {
        expect(fn () => app(BangkitkanDbr::class)->handle($r1, dbrUser($peran)))->toThrow(AuthorizationException::class);
    }
    expect(app(BangkitkanDbr::class)->handle($r2, dbrUser('admin-bmn')))->toBeInstanceOf(DbrVersi::class);
});

it('alur penuh: draf → disetujui PIC → disahkan pejabat, penandatangan dan kop tersimpan pada snapshot (US-DBR-01)', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    Aset::factory()->diRuangan($r)->count(3)->create();
    Pengaturan::simpan('kota_surat', 'Tasikmalaya');
    Pengaturan::simpan('penandatangan_jabatan', 'Kasubag Umum');

    $dbr = app(BangkitkanDbr::class)->handle($r, $pic);
    app(SetujuiDbrOlehPic::class)->handle($dbr, $pic);

    $dbr->refresh();
    expect($dbr)->status->toBe(StatusDbr::DisetujuiPic)->disetujui_pic_oleh->toBe($pic->id)
        ->and($dbr->snapshot['penandatangan']['pic'])->toMatchArray(['nama' => 'Bu PIC', 'nip' => '198001012005012001'])
        ->and($dbr->snapshot['penandatangan']['pejabat'])->toBeNull();

    app(SahkanDbr::class)->handle($dbr, $pejabat);

    $dbr->refresh();
    expect($dbr)->status->toBe(StatusDbr::Disahkan)->disahkan_oleh->toBe($pejabat->id)->and($dbr->disahkan_pada)->not->toBeNull()
        ->and($dbr->snapshot['penandatangan']['pejabat'])->toMatchArray(['nama' => 'Pak Pejabat', 'nip' => '197001011995031001', 'jabatan' => 'Kasubag Umum'])
        ->and($dbr->snapshot['kop']['kota_surat'])->toBe('Tasikmalaya')
        ->and($dbr->snapshot['ringkasan']['jumlah'])->toBe(3);
});

it('otorisasi tahap: PIC ruangan lain, admin, dan pejabat tidak dapat menyetujui sebagai PIC; hanya pejabat mengesahkan', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    $dbr = app(BangkitkanDbr::class)->handle($r, $pic);

    expect(fn () => app(SetujuiDbrOlehPic::class)->handle($dbr, dbrUser('pic-ruangan', [dbrRuang('R-9')])))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetujuiDbrOlehPic::class)->handle($dbr, dbrUser('admin-bmn')))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetujuiDbrOlehPic::class)->handle($dbr, $pejabat))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetujuiDbrOlehPic::class)->handle($dbr, dbrUser('pimpinan')))->toThrow(AuthorizationException::class);

    app(SetujuiDbrOlehPic::class)->handle($dbr, $pic);

    expect(fn () => app(SahkanDbr::class)->handle($dbr, $pic))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SahkanDbr::class)->handle($dbr, dbrUser('admin-bmn')))->toThrow(AuthorizationException::class)
        ->and(app(SahkanDbr::class)->handle($dbr, $pejabat)->status)->toBe(StatusDbr::Disahkan);
});

it('DBL: persetujuan tahap PIC dilakukan admin-bmn', function () {
    $admin = dbrUser('admin-bmn');
    $pejabat = dbrUser('pejabat-penatausahaan');
    Aset::factory()->create(['ruangan_id' => null, 'lokasi_lainnya' => 'Lapangan']);
    $dbl = app(BangkitkanDbr::class)->handle(null, $admin);

    app(SetujuiDbrOlehPic::class)->handle($dbl, $admin);
    expect(app(SahkanDbr::class)->handle($dbl, $pejabat)->status)->toBe(StatusDbr::Disahkan);
});

it('tidak dapat disahkan dari draf, atau disetujui PIC dua kali', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    $dbr = app(BangkitkanDbr::class)->handle($r, $pic);

    expect(fn () => app(SahkanDbr::class)->handle($dbr, $pejabat))->toThrow(ValidationException::class, 'sudah disetujui PIC');

    app(SetujuiDbrOlehPic::class)->handle($dbr, $pic);
    expect(fn () => app(SetujuiDbrOlehPic::class)->handle($dbr, $pic))->toThrow(ValidationException::class)
        ->and(fn () => app(BangkitkanDbr::class)->handle($r, $pic))->toThrow(ValidationException::class, 'menunggu pengesahan');
});

it('pengesahan ditolak bila isi aset berubah sejak disetujui PIC; kembalikan lalu ulangi', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    $aset = Aset::factory()->diRuangan($r)->create(['nama' => 'Kursi']);
    $dbr = app(BangkitkanDbr::class)->handle($r, $pic);
    app(SetujuiDbrOlehPic::class)->handle($dbr, $pic);

    $aset->update(['kondisi' => KondisiAset::RusakRingan]);

    expect(fn () => app(SahkanDbr::class)->handle($dbr, $pejabat))->toThrow(ValidationException::class, 'berubah sejak disetujui PIC');

    expect(fn () => app(KembalikanDbr::class)->handle($dbr, $pejabat, ' '))->toThrow(ValidationException::class);
    app(KembalikanDbr::class)->handle($dbr, $pejabat, 'Kondisi kursi berubah');
    $dbr->refresh();
    expect($dbr)->status->toBe(StatusDbr::Draf)->catatan->toBe('Kondisi kursi berubah')->disetujui_pic_oleh->toBeNull()
        ->and($dbr->snapshot['penandatangan']['pic'])->toBeNull();

    $ulang = app(BangkitkanDbr::class)->handle($r, $pic);
    expect($ulang->id)->toBe($dbr->id)->and(collect($ulang->snapshot['aset'])->firstWhere('nama', 'Kursi')['kondisi'])->toBe('RR');
    app(SetujuiDbrOlehPic::class)->handle($ulang, $pic);
    expect(app(SahkanDbr::class)->handle($ulang, $pejabat)->status)->toBe(StatusDbr::Disahkan);
});

it('hanya DBR yang menunggu pengesahan yang dapat dikembalikan, oleh pejabat atau PIC-nya', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    $dbr = app(BangkitkanDbr::class)->handle($r, $pic);

    expect(fn () => app(KembalikanDbr::class)->handle($dbr, $pic, 'x'))->toThrow(AuthorizationException::class); // masih draf

    app(SetujuiDbrOlehPic::class)->handle($dbr, $pic);
    expect(fn () => app(KembalikanDbr::class)->handle($dbr, dbrUser('civitas'), 'x'))->toThrow(AuthorizationException::class)
        ->and(app(KembalikanDbr::class)->handle($dbr, $pic, 'Periksa ulang')->status)->toBe(StatusDbr::Draf);
});

it('mutasi menandai DBR ruangan asal dan tujuan perlu_diperbarui (BR-06, BR-13)', function () {
    [$asal, $pic, $pejabat] = dbrSkenario();
    $tujuan = dbrRuang('R-T');
    $picT = dbrUser('pic-ruangan', [$tujuan]);
    $aset = Aset::factory()->diRuangan($asal)->create();
    $dbrAsal = dbrDisahkan($asal, $pic, $pejabat);
    $dbrTujuan = dbrDisahkan($tujuan, $picT, $pejabat);
    $rLain = dbrRuang('R-L');
    $dbrLain = dbrDisahkan($rLain, dbrUser('pic-ruangan', [$rLain]), $pejabat);   // ruangan tak terkait

    $mutasi = app(AjukanMutasi::class)->handle($asal, $tujuan, [$aset->id], 'Pindah', $pic);
    expect($dbrAsal->fresh()->status)->toBe(StatusDbr::Disahkan);   // belum disetujui → belum berubah

    app(SetujuiMutasi::class)->handle($mutasi, dbrUser('admin-bmn'));

    expect($dbrAsal->fresh()->status)->toBe(StatusDbr::PerluDiperbarui)
        ->and($dbrTujuan->fresh()->status)->toBe(StatusDbr::PerluDiperbarui)
        ->and($dbrLain->fresh()->status)->toBe(StatusDbr::Disahkan);
});

it('perubahan aset yang tampil di DBR (kondisi, status, nama, tambah, hapus) menandai perlu_diperbarui; yang tidak tampil tidak', function (callable $ubah, bool $menandai) {
    [$r, $pic, $pejabat] = dbrSkenario();
    $aset = Aset::factory()->diRuangan($r)->create(['nama' => 'Kursi']);
    $dbr = dbrDisahkan($r, $pic, $pejabat);

    $ubah($aset, $r);

    expect($dbr->fresh()->status)->toBe($menandai ? StatusDbr::PerluDiperbarui : StatusDbr::Disahkan);
})->with([
    'kondisi' => [fn ($a) => $a->update(['kondisi' => 'RB']), true],
    'nama' => [fn ($a) => $a->update(['nama' => 'Kursi Baru']), true],
    'status' => [fn ($a) => $a->update(['status' => 'dalam_perbaikan']), true],
    'keterangan' => [fn ($a) => $a->update(['keterangan' => 'catatan']), true],
    'aset baru di ruangan' => [fn ($a, $r) => Aset::factory()->diRuangan($r)->create(), true],
    'hapus lunak' => [fn ($a) => $a->delete(), true],
    'nilai perolehan (tak tampil)' => [fn ($a) => $a->update(['nilai_perolehan' => '1.00']), false],
    'dapat dipinjam (tak tampil)' => [fn ($a) => $a->update(['dapat_dipinjam' => true]), false],
    'aset ruangan lain' => [fn ($a) => Aset::factory()->create(), false],
]);

it('DBL ditandai perlu_diperbarui saat aset berlokasi lainnya berubah', function () {
    $admin = dbrUser('admin-bmn');
    $pejabat = dbrUser('pejabat-penatausahaan');
    $aset = Aset::factory()->create(['ruangan_id' => null, 'lokasi_lainnya' => 'Lapangan']);
    $dbl = app(BangkitkanDbr::class)->handle(null, $admin);
    app(SetujuiDbrOlehPic::class)->handle($dbl, $admin);
    app(SahkanDbr::class)->handle($dbl, $pejabat);

    $aset->update(['nama' => 'Tiang Bendera']);

    expect($dbl->fresh()->status)->toBe(StatusDbr::PerluDiperbarui);
});

it('setelah perlu_diperbarui, versi baru dibangkitkan; versi lama tetap sebagai dokumen sah sebelumnya', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    $aset = Aset::factory()->diRuangan($r)->create(['nama' => 'Kursi Lama']);
    $v1 = dbrDisahkan($r, $pic, $pejabat);
    $aset->update(['nama' => 'Kursi Baru']);

    $v2 = app(BangkitkanDbr::class)->handle($r, $pic);

    expect($v2)->versi->toBe(2)->status->toBe(StatusDbr::Draf)->and($v2->id)->not->toBe($v1->id)
        ->and($v1->fresh())->status->toBe(StatusDbr::PerluDiperbarui)
        ->and($v1->fresh()->snapshot['aset'][0]['nama'])->toBe('Kursi Lama')
        ->and($v2->snapshot['aset'][0]['nama'])->toBe('Kursi Baru');
});

it('PDF dirender dari snapshot: versi lama tidak berubah setelah aset berpindah/diganti (BR-13)', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    $aset = Aset::factory()->diRuangan($r)->create(['nama' => 'Proyektor Lama', 'merk_tipe' => 'Epson', 'kode_barang' => '3100203002', 'nup' => 77, 'tahun_perolehan' => 2019]);
    $dbr = dbrDisahkan($r, $pic, $pejabat);
    $render = fn () => view('pdf.dbr', ['dbr' => $dbr->fresh(), 's' => $dbr->fresh()->snapshot, 'draf' => false])->render();
    $sebelum = $render();

    $aset->update(['nama' => 'Proyektor Baru', 'ruangan_id' => dbrRuang('R-PINDAH')->id, 'kondisi' => 'RB']);
    Pengaturan::simpan('instansi_baris2', 'NAMA KOP BARU');

    $sesudah = $render();
    expect($sesudah)->toBe($sebelum)
        ->and($sesudah)->toContain('Proyektor Lama')->toContain('3100203002')->toContain('Epson')->toContain('2019')->toContain('Bu PIC')->toContain('198001012005012001')
        ->toContain('Pak Pejabat')->toContain('197001011995031001')->toContain('Daftar Barang Ruangan')
        ->not->toContain('Proyektor Baru')->not->toContain('NAMA KOP BARU')->not->toContain('DRAF');
});

it('PDF memuat kolom wajib dan tanda air DRAF bila belum disahkan', function () {
    [$r, $pic] = dbrSkenario();
    Aset::factory()->diRuangan($r)->create(['nama' => 'Meja', 'keterangan' => 'Baik']);
    $dbr = app(BangkitkanDbr::class)->handle($r, $pic);

    $html = view('pdf.dbr', ['dbr' => $dbr, 's' => $dbr->snapshot, 'draf' => true])->render();

    foreach (['No', 'Kode Barang', 'NUP', 'Nama Barang', 'Merk/Tipe', 'Tahun', 'Kondisi', 'Keterangan', 'DRAF'] as $kolom) {
        expect($html)->toContain($kolom);
    }
});

it('endpoint PDF men-stream application/pdf dari snapshot dan membatasi akses', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    Aset::factory()->diRuangan($r)->create();
    $sah = dbrDisahkan($r, $pic, $pejabat);
    $draf = app(BangkitkanDbr::class)->handle(dbrRuang('R-2'), dbrUser('admin-bmn'));

    $this->get('/cetak/dbr/'.$sah->id)->assertRedirect('/login');

    $this->actingAs($pic);
    $respons = $this->get('/cetak/dbr/'.$sah->id);
    $respons->assertOk();
    expect($respons->headers->get('content-type'))->toContain('application/pdf')->and($respons->getContent())->toStartWith('%PDF');

    $this->actingAs($pic)->get('/cetak/dbr/'.$draf->id)->assertForbidden();             // ruangan lain
    $this->actingAs(dbrUser('pimpinan'))->get('/cetak/dbr/'.$sah->id)->assertOk();     // pimpinan: yang disahkan
    $this->actingAs(dbrUser('pimpinan'))->get('/cetak/dbr/'.$draf->id)->assertForbidden(); // pimpinan: bukan draf
    $this->actingAs(dbrUser('civitas'))->get('/cetak/dbr/'.$sah->id)->assertForbidden();
    $this->actingAs(dbrUser('pejabat-penatausahaan'))->get('/cetak/dbr/'.$draf->id)->assertOk();
});

it('endpoint PDF memberikan snapshot (bukan data kini) ke tampilan', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    $aset = Aset::factory()->diRuangan($r)->create(['nama' => 'Nama Snapshot']);
    $dbr = dbrDisahkan($r, $pic, $pejabat);
    $aset->update(['nama' => 'Nama Berubah']);
    $dbr = $dbr->fresh();

    Pdf::shouldReceive('loadView')->once()
        ->with('pdf.dbr', Mockery::on(fn ($d) => $d['s'] === $dbr->snapshot && $d['draf'] === false && $d['s']['aset'][0]['nama'] === 'Nama Snapshot'))
        ->andReturnSelf();
    Pdf::shouldReceive('setPaper')->with('a4', 'landscape')->andReturnSelf();
    Pdf::shouldReceive('stream')->andReturn(response('%PDF-tiruan', 200, ['Content-Type' => 'application/pdf']));

    $this->actingAs($pejabat)->get('/cetak/dbr/'.$dbr->id)->assertOk();
});

it('panel: PIC melihat DBR ruangannya, bangkitkan, setujui; pejabat mengesahkan', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    $r2 = dbrRuang('R-2');
    Aset::factory()->diRuangan($r)->count(2)->create();
    $lain = app(BangkitkanDbr::class)->handle($r2, dbrUser('admin-bmn'));

    $this->actingAs($pic);
    Livewire::test(DaftarDbr::class)->callAction('bangkitkan', ['ruangan_id' => $r->id])->assertHasNoActionErrors();
    $dbr = DbrVersi::query()->where('ruangan_id', $r->id)->firstOrFail();

    Livewire::test(DaftarDbr::class)->assertCanSeeTableRecords([$dbr])->assertCanNotSeeTableRecords([$lain]);
    $this->get('/admin/dbr/'.$lain->id)->assertNotFound();

    Livewire::test(LihatDbr::class, ['record' => $dbr->getRouteKey()])
        ->assertActionVisible('setujuiPic')->assertActionHidden('sahkan')->assertActionVisible('cetak')
        ->callAction('setujuiPic');
    expect($dbr->fresh()->status)->toBe(StatusDbr::DisetujuiPic);

    $this->actingAs($pejabat);
    Livewire::test(LihatDbr::class, ['record' => $dbr->getRouteKey()])
        ->assertActionVisible('sahkan')->assertActionHidden('setujuiPic')->assertActionVisible('kembalikan')
        ->callAction('sahkan');
    expect($dbr->fresh()->status)->toBe(StatusDbr::Disahkan);
});

it('panel: admin dapat membangkitkan DBL; PIC tidak melihat opsi DBL; pimpinan hanya melihat yang disahkan', function () {
    [$r, $pic, $pejabat] = dbrSkenario();
    Aset::factory()->create(['ruangan_id' => null, 'lokasi_lainnya' => 'Halaman']);
    $this->actingAs(dbrUser('admin-bmn'));
    Livewire::test(DaftarDbr::class)->callAction('bangkitkan', ['ruangan_id' => 'dbl'])->assertHasNoActionErrors();
    expect(DbrVersi::query()->where('jenis', 'dbl')->count())->toBe(1);

    $draf = app(BangkitkanDbr::class)->handle($r, $pic);
    $sah = dbrDisahkan(dbrRuang('R-3'), dbrUser('pic-ruangan', [Ruangan::query()->where('kode', 'R-3')->first()]), $pejabat);

    $this->actingAs(dbrUser('pimpinan'));
    Livewire::test(DaftarDbr::class)->assertCanSeeTableRecords([$sah])->assertCanNotSeeTableRecords([$draf]);
});
