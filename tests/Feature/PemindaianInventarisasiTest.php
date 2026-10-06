<?php

use App\Actions\Inventarisasi\BuatPeriode;
use App\Actions\Inventarisasi\BukaPeriode;
use App\Actions\Inventarisasi\CatatHasilPindai;
use App\Actions\Inventarisasi\CatatTemuanBerlebih;
use App\Actions\Inventarisasi\SelesaikanInventarisasiRuangan;
use App\Enums\HasilInventarisasi;
use App\Enums\JenisInventarisasi;
use App\Enums\KondisiAset;
use App\Enums\StatusInventarisasiRuangan;
use App\Livewire\InventarisasiRuanganHalaman;
use App\Models\Aset;
use App\Models\HasilInventarisasi as ModelHasil;
use App\Models\InventarisasiRuangan;
use App\Models\LabelLama;
use App\Models\Ruangan;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(PeranDanIzinSeeder::class));

function pmUser(string $peran, array $ruangan = []): User
{
    $u = User::factory()->create();
    $u->assignRole($peran);
    $u->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));

    return $u;
}

function pmRuang(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

/** @return array{0: InventarisasiRuangan, 1: User, 2: User} inventarisasi ruangan (periode berjalan), PIC, admin */
function pmBerjalan(Ruangan $r): array
{
    $admin = pmUser('admin-bmn');
    $p = app(BuatPeriode::class)->handle('Sensus', JenisInventarisasi::Sensus, Carbon::parse('2026-11-01'), null, [$r->id], $admin);
    app(BukaPeriode::class)->handle($p, $admin);

    return [$p->ruangan->first()->fresh(), pmUser('pic-ruangan', [$r]), $admin];
}

it('mencatat aset ditemukan dengan kondisi sama, dan menandai ruangan berjalan pada pemindaian pertama', function () {
    $r = pmRuang('R-1');
    [$inv, $pic] = pmBerjalan($r);
    $aset = Aset::factory()->diRuangan($r)->create(['kondisi' => 'B']);

    $hasil = app(CatatHasilPindai::class)->handle($inv, $aset, $pic);

    expect($hasil)->hasil->toBe(HasilInventarisasi::Ditemukan)->kondisi_ditemukan->toBeNull()->dipindai_oleh->toBe($pic->id)
        ->and($hasil->dipindai_pada)->not->toBeNull()
        ->and($inv->fresh()->status)->toBe(StatusInventarisasiRuangan::Berjalan);
});

it('kondisi berbeda → kondisi_berubah dengan kondisi ditemukan; kondisi aset belum berubah sampai periode ditutup', function () {
    $r = pmRuang('R-1');
    [$inv, $pic] = pmBerjalan($r);
    $aset = Aset::factory()->diRuangan($r)->create(['kondisi' => 'B']);

    $hasil = app(CatatHasilPindai::class)->handle($inv, $aset, $pic, KondisiAset::RusakRingan);

    expect($hasil)->hasil->toBe(HasilInventarisasi::KondisiBerubah)->kondisi_ditemukan->toBe(KondisiAset::RusakRingan)
        ->and($aset->fresh()->kondisi)->toBe(KondisiAset::Baik);
});

it('memindai ulang memperbarui hasil yang sama (tidak ada baris ganda)', function () {
    $r = pmRuang('R-1');
    [$inv, $pic] = pmBerjalan($r);
    $aset = Aset::factory()->diRuangan($r)->create(['kondisi' => 'B']);

    app(CatatHasilPindai::class)->handle($inv, $aset, $pic, KondisiAset::RusakBerat);
    app(CatatHasilPindai::class)->handle($inv, $aset, $pic);   // dikoreksi kembali: kondisi sama

    expect(ModelHasil::count())->toBe(1)->and(ModelHasil::first())->hasil->toBe(HasilInventarisasi::Ditemukan)->kondisi_ditemukan->toBeNull();
});

it('memindai aset ruangan lain ditolak dengan peringatan jelas dan tidak dicatat (BR-14)', function () {
    [$r1, $r2] = [pmRuang('R-1'), pmRuang('R-2')];
    [$inv, $pic] = pmBerjalan($r1);
    $asing = Aset::factory()->diRuangan($r2)->create(['nama' => 'Proyektor Asing']);
    $lokasiLain = Aset::factory()->create(['ruangan_id' => null, 'lokasi_lainnya' => 'Selasar', 'nama' => 'Bangku']);

    expect(fn () => app(CatatHasilPindai::class)->handle($inv, $asing, $pic))->toThrow(ValidationException::class, 'Ruang R-2')
        ->and(fn () => app(CatatHasilPindai::class)->handle($inv, $lokasiLain, $pic))->toThrow(ValidationException::class, 'Selasar');
    expect(ModelHasil::count())->toBe(0);
});

it('aset dihapus ditolak; pindai di ruangan selesai atau periode tidak berjalan ditolak', function () {
    $r = pmRuang('R-1');
    [$inv, $pic, $admin] = pmBerjalan($r);
    $hapus = Aset::factory()->diRuangan($r)->create(['status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK', 'tanggal_sk_penghapusan' => '2026-01-01']);
    $aset = Aset::factory()->diRuangan($r)->create();

    expect(fn () => app(CatatHasilPindai::class)->handle($inv, $hapus, $pic))->toThrow(ValidationException::class, 'dihapus');

    app(SelesaikanInventarisasiRuangan::class)->handle($inv, $pic);
    expect(fn () => app(CatatHasilPindai::class)->handle($inv->fresh(), $aset, $pic))->toThrow(ValidationException::class, 'sudah selesai');

    $inv->periode->update(['status' => 'ditutup']);
    expect(fn () => app(CatatHasilPindai::class)->handle($inv->fresh(), $aset, $pic))->toThrow(AuthorizationException::class);
});

it('hanya PIC ruangan itu, petugas ditugaskan, atau admin yang dapat mencatat hasil', function () {
    [$r1, $r2] = [pmRuang('R-1'), pmRuang('R-2')];
    [$inv, $pic, $admin] = pmBerjalan($r1);
    $aset = Aset::factory()->diRuangan($r1)->create();

    expect(fn () => app(CatatHasilPindai::class)->handle($inv, $aset, pmUser('pic-ruangan', [$r2])))->toThrow(AuthorizationException::class);
    foreach (['pimpinan', 'pejabat-penatausahaan', 'civitas'] as $peran) {
        expect(fn () => app(CatatHasilPindai::class)->handle($inv, $aset, pmUser($peran)))->toThrow(AuthorizationException::class);
    }
    expect(app(CatatHasilPindai::class)->handle($inv, $aset, $admin))->toBeInstanceOf(ModelHasil::class);
});

it('temuan berlebih: deskripsi wajib, tanpa aset, foto sebagai tautan Drive', function () {
    $r = pmRuang('R-1');
    [$inv, $pic] = pmBerjalan($r);

    expect(fn () => app(CatatTemuanBerlebih::class)->handle($inv, 'x', null, $pic))->toThrow(ValidationException::class)
        ->and(fn () => app(CatatTemuanBerlebih::class)->handle($inv, 'Kursi tanpa label', 'https://example.com/foto.jpg', $pic))->toThrow(ValidationException::class);
    expect(ModelHasil::count())->toBe(0);

    $hasil = app(CatatTemuanBerlebih::class)->handle($inv, 'Kursi tanpa label', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view', $pic);

    expect($hasil)->hasil->toBe(HasilInventarisasi::Berlebih)->aset_id->toBeNull()->deskripsi_temuan->toBe('Kursi tanpa label')
        ->and($hasil->tautanBerkas()->first())->jenis->toBe('foto')->drive_file_id->toBe('1AbCdEfGhIjKlMnOpQrStUv')
        ->and($inv->fresh()->status)->toBe(StatusInventarisasiRuangan::Berjalan);
});

it('selesai ruangan: aset yang belum dipindai otomatis tidak_ditemukan; hilang/dihapus dan yang sudah dipindai tidak', function () {
    $r = pmRuang('R-1');
    [$inv, $pic] = pmBerjalan($r);
    [$ada, $hilangLuput] = [Aset::factory()->diRuangan($r)->create(['nama' => 'Ada']), Aset::factory()->diRuangan($r)->create(['nama' => 'Luput'])];
    Aset::factory()->diRuangan($r)->create(['nama' => 'SudahHilang', 'status' => 'hilang']);
    Aset::factory()->diRuangan($r)->create(['nama' => 'Dihapus', 'status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK', 'tanggal_sk_penghapusan' => '2026-01-01']);
    Aset::factory()->create(['nama' => 'Ruangan Lain']);
    app(CatatHasilPindai::class)->handle($inv, $ada, $pic);

    $selesai = app(SelesaikanInventarisasiRuangan::class)->handle($inv, $pic);

    expect($selesai)->status->toBe(StatusInventarisasiRuangan::Selesai)->and($selesai->selesai_pada)->not->toBeNull();
    $hasil = ModelHasil::query()->with('aset')->get()->mapWithKeys(fn ($h) => [$h->aset->nama => $h->hasil->value])->all();
    expect($hasil)->toBe(['Ada' => 'ditemukan', 'Luput' => 'tidak_ditemukan']);

    expect(fn () => app(SelesaikanInventarisasiRuangan::class)->handle($inv, $pic))->toThrow(ValidationException::class, 'sudah selesai');
});

it('progres = aset ditemukan / aset seharusnya ada', function () {
    $r = pmRuang('R-1');
    [$inv, $pic] = pmBerjalan($r);
    $aset = Aset::factory()->diRuangan($r)->count(4)->create();
    Aset::factory()->diRuangan($r)->create(['status' => 'hilang']);   // tidak dihitung

    expect(SelesaikanInventarisasiRuangan::progres($inv))->toBe(0);

    app(CatatHasilPindai::class)->handle($inv, $aset[0], $pic);
    app(CatatHasilPindai::class)->handle($inv, $aset[1], $pic, KondisiAset::RusakRingan);
    expect(SelesaikanInventarisasiRuangan::progres($inv->fresh()))->toBe(50);

    app(CatatHasilPindai::class)->handle($inv, $aset[2], $pic);
    app(CatatHasilPindai::class)->handle($inv, $aset[3], $pic);
    expect(SelesaikanInventarisasiRuangan::progres($inv->fresh()))->toBe(100);
});

it('halaman pemindaian: akses hanya yang berhak dan hanya saat periode berjalan', function () {
    [$r1, $r2] = [pmRuang('R-1'), pmRuang('R-2')];
    [$inv, $pic, $admin] = pmBerjalan($r1);
    $url = route('inventarisasi.ruangan', ['periode' => $inv->periode_id, 'ruangan' => $inv->ruangan_id]);

    $this->get($url)->assertRedirect('/login');
    $this->actingAs(pmUser('pic-ruangan', [$r2]))->get($url)->assertForbidden();
    $this->actingAs(pmUser('pimpinan'))->get($url)->assertForbidden();
    $this->actingAs($pic)->get($url)->assertOk()->assertSee('Inventarisasi: Ruang R-1');
    $this->actingAs($admin)->get($url)->assertOk();
    $this->get(route('inventarisasi.ruangan', ['periode' => $inv->periode_id, 'ruangan' => $r2->id]))->assertNotFound();

    $inv->periode->update(['status' => 'ditutup']);
    $this->actingAs($pic)->get($url)->assertForbidden();
});

it('halaman: pindai URL baru dan label lama menandai ditemukan; kode tak dikenal memberi peringatan', function () {
    $r = pmRuang('R-1');
    [$inv, $pic] = pmBerjalan($r);
    $baru = Aset::factory()->diRuangan($r)->create(['nama' => 'Meja Baru']);
    $lama = Aset::factory()->diRuangan($r)->create(['nama' => 'Lemari Lama']);
    LabelLama::query()->create(['teks' => 'MBL-123456-1', 'aset_id' => $lama->id]);
    $this->actingAs($pic);

    $k = Livewire::test(InventarisasiRuanganHalaman::class, ['periode' => $inv->periode_id, 'ruangan' => $r->id])
        ->assertSee('Meja Baru')->assertSee('Lemari Lama')->assertSee('Belum dipindai')->assertSee('0%');

    $k->set('teks', url('/a/'.$baru->id))->call('pindai')->assertSee('Meja Baru ditandai ditemukan')->assertSee('50%')
        ->set('teks', 'mbl-123456-1')->call('pindai')->assertSee('Lemari Lama ditandai ditemukan')->assertSee('100%')
        ->set('teks', 'TIDAK-ADA-9')->call('pindai')->assertSee('Kode tidak ditemukan');

    expect(ModelHasil::query()->where('hasil', 'ditemukan')->count())->toBe(2);
});

it('halaman: memindai aset ruangan lain menampilkan peringatan, tanpa mencatat', function () {
    [$r1, $r2] = [pmRuang('R-1'), pmRuang('R-2')];
    [$inv, $pic] = pmBerjalan($r1);
    $asing = Aset::factory()->diRuangan($r2)->create(['nama' => 'Printer Ruang Dua']);
    $this->actingAs($pic);

    Livewire::test(InventarisasiRuanganHalaman::class, ['periode' => $inv->periode_id, 'ruangan' => $r1->id])
        ->set('teks', $asing->id)->call('pindai')
        ->assertSee('Printer Ruang Dua')->assertSee('Ruang R-2')->assertSee('bukan di ruangan ini');

    expect(ModelHasil::count())->toBe(0);
});

it('halaman: koreksi kondisi menghasilkan kondisi_berubah dan menampilkan pengingat cetak ulang label', function () {
    $r = pmRuang('R-1');
    [$inv, $pic] = pmBerjalan($r);
    $aset = Aset::factory()->diRuangan($r)->create(['nama' => 'Kursi Ambigu', 'kondisi' => 'B', 'label_perlu_cetak_ulang' => true]);
    $this->actingAs($pic);

    Livewire::test(InventarisasiRuanganHalaman::class, ['periode' => $inv->periode_id, 'ruangan' => $r->id])
        ->assertSee('Label perlu dicetak ulang dan ditempel')
        ->call('koreksiKondisi', $aset->id, 'RR')
        ->assertSee('Kondisi berubah')->assertSee('RR');

    expect(ModelHasil::first())->hasil->toBe(HasilInventarisasi::KondisiBerubah)->kondisi_ditemukan->toBe(KondisiAset::RusakRingan);
});

it('halaman: temuan berlebih dengan foto tautan, validasi, dan tampil di daftar', function () {
    $r = pmRuang('R-1');
    [$inv, $pic] = pmBerjalan($r);
    $this->actingAs($pic);
    $parameter = ['periode' => $inv->periode_id, 'ruangan' => $r->id];

    Livewire::test(InventarisasiRuanganHalaman::class, $parameter)
        ->set('deskripsiTemuan', '')->call('catatTemuanBerlebih')->assertHasErrors('deskripsiTemuan')
        ->set('deskripsiTemuan', 'AC tanpa label')->set('fotoTemuan', 'https://example.com/x.jpg')->call('catatTemuanBerlebih')->assertHasErrors('fotoTemuan')
        ->set('fotoTemuan', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view')->call('catatTemuanBerlebih')
        ->assertHasNoErrors()->assertSee('Temuan berlebih dicatat')->assertSee('AC tanpa label');

    expect(ModelHasil::query()->where('hasil', 'berlebih')->count())->toBe(1);
});

it('halaman: selesai ruangan meminta konfirmasi, mencatat tidak ditemukan, dan mengunci pemindaian', function () {
    $r = pmRuang('R-1');
    [$inv, $pic] = pmBerjalan($r);
    $a = Aset::factory()->diRuangan($r)->create(['nama' => 'Ditemukan']);
    $b = Aset::factory()->diRuangan($r)->create(['nama' => 'Tidak Ada']);
    $this->actingAs($pic);

    $k = Livewire::test(InventarisasiRuanganHalaman::class, ['periode' => $inv->periode_id, 'ruangan' => $r->id])
        ->call('tandaiDitemukan', $a->id)
        ->call('$set', 'konfirmasiSelesai', true)->assertSee('tidak ditemukan')
        ->call('selesai')->assertSee('Inventarisasi ruangan selesai')->assertSee('Tidak ditemukan');

    $k->assertDontSee('Selesai ruangan')->assertDontSee('Catat temuan berlebih');
    expect(ModelHasil::query()->where('aset_id', $b->id)->first()->hasil)->toBe(HasilInventarisasi::TidakDitemukan)
        ->and($inv->fresh()->status)->toBe(StatusInventarisasiRuangan::Selesai);

    $k->call('tandaiDitemukan', $b->id)->assertSee('sudah selesai');
});
