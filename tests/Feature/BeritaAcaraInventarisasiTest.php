<?php

use App\Actions\Inventarisasi\BuatPeriode;
use App\Actions\Inventarisasi\BukaPeriode;
use App\Actions\Inventarisasi\CatatHasilPindai;
use App\Actions\Inventarisasi\CatatTemuanBerlebih;
use App\Actions\Inventarisasi\SahkanBeritaAcara;
use App\Actions\Inventarisasi\SelesaikanInventarisasiRuangan;
use App\Actions\Inventarisasi\TetapkanAsetHilang;
use App\Actions\Inventarisasi\TutupPeriode;
use App\Enums\JenisInventarisasi;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\Pages\LihatPeriodeInventarisasi;
use App\Filament\Resources\Inventarisasi\RelationManagers\TidakDitemukanRelationManager;
use App\Filament\Widgets\PeringatanInventarisasiWidget;
use App\Models\Aset;
use App\Models\HasilInventarisasi;
use App\Models\PeriodeInventarisasi;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\Pengaturan;
use App\Support\PeringatanInventarisasi;
use Carbon\Carbon;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config(['aset.lock_tunggu_detik' => 0]);
});

function baUser(string $peran, array $ruangan = [], array $atribut = []): User
{
    $u = User::factory()->create($atribut);
    $u->assignRole($peran);
    $u->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));
    $u->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $u;
}

function baRuang(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

/**
 * Skenario kecil: 2 ruangan; R-1: A (ditemukan), B (kondisi berubah B→RR), C (tidak ditemukan), 1 temuan berlebih;
 * R-2: D (ditemukan). Periode berjalan, kedua ruangan selesai.
 *
 * @return array{p: PeriodeInventarisasi, admin: User, pejabat: User, picR1: User, aset: array<string, Aset>, ruangan: array{0: Ruangan, 1: Ruangan}}
 */
function baSkenario(bool $selesai = true): array
{
    [$r1, $r2] = [baRuang('R-1'), baRuang('R-2')];
    $admin = baUser('admin-bmn', [], ['name' => 'Admin BMN', 'nip' => '198501012010011001']);
    $pejabat = baUser('pejabat-penatausahaan', [], ['name' => 'Pak Pejabat', 'nip' => '197001011995031001']);
    $picR1 = baUser('pic-ruangan', [$r1]);
    $picR2 = baUser('pic-ruangan', [$r2]);
    $a = Aset::factory()->diRuangan($r1)->create(['nama' => 'Aset A', 'kode_barang' => '3100102001', 'nup' => 1, 'kondisi' => 'B']);
    $b = Aset::factory()->diRuangan($r1)->create(['nama' => 'Aset B', 'kode_barang' => '3100102001', 'nup' => 2, 'kondisi' => 'B']);
    $c = Aset::factory()->diRuangan($r1)->create(['nama' => 'Aset C', 'kode_barang' => '3100102001', 'nup' => 3, 'kondisi' => 'B']);
    $d = Aset::factory()->diRuangan($r2)->create(['nama' => 'Aset D', 'kode_barang' => '3100102002', 'nup' => 4, 'kondisi' => 'B']);

    $p = app(BuatPeriode::class)->handle('Sensus 2026', JenisInventarisasi::Sensus, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'), [$r1->id, $r2->id], $admin);
    app(BukaPeriode::class)->handle($p, $admin);
    $inv1 = $p->ruangan()->where('ruangan_id', $r1->id)->first();
    $inv2 = $p->ruangan()->where('ruangan_id', $r2->id)->first();

    app(CatatHasilPindai::class)->handle($inv1, $a, $picR1);
    app(CatatHasilPindai::class)->handle($inv1, $b, $picR1, KondisiAset::RusakRingan);
    app(CatatTemuanBerlebih::class)->handle($inv1, 'Lemari tanpa label', null, $picR1);
    app(CatatHasilPindai::class)->handle($inv2, $d, $picR2);

    if ($selesai) {
        app(SelesaikanInventarisasiRuangan::class)->handle($inv1, $picR1);
        app(SelesaikanInventarisasiRuangan::class)->handle($inv2, $picR2);
    }

    return ['p' => $p->fresh(), 'admin' => $admin, 'pejabat' => $pejabat, 'picR1' => $picR1, 'aset' => compact('a', 'b', 'c', 'd'), 'ruangan' => [$r1, $r2]];
}

it('menutup periode: kondisi berubah diterapkan dengan riwayat sumber inventarisasi; tidak ditemukan belum hilang (BR-14)', function () {
    ['p' => $p, 'admin' => $admin, 'aset' => $aset] = baSkenario();

    $tutup = app(TutupPeriode::class)->handle($p, $admin);

    expect($tutup)->status->toBe(StatusPeriodeInventarisasi::Ditutup)->and($tutup->ditutup_pada)->not->toBeNull()->and($tutup->berita_acara)->not->toBeNull();

    expect($aset['b']->fresh()->kondisi)->toBe(KondisiAset::RusakRingan);
    $riwayat = $aset['b']->riwayatKondisi()->first();
    expect($riwayat)->dari->toBe('B')->ke->toBe('RR')->sumber->toBe('inventarisasi')->sumber_id->toBe($p->id)->oleh->toBe($admin->id);

    expect($aset['a']->fresh()->kondisi)->toBe(KondisiAset::Baik)->and($aset['a']->riwayatKondisi()->where('sumber', 'inventarisasi')->count())->toBe(0)
        ->and($aset['c']->fresh()->status)->toBe(StatusAset::Aktif);   // belum diverifikasi → tetap aktif
});

it('snapshot berita acara memuat rekap per ruangan, daftar selisih, dan kop', function () {
    Pengaturan::simpan('kota_surat', 'Tasikmalaya');
    ['p' => $p, 'admin' => $admin] = baSkenario();

    $s = app(TutupPeriode::class)->handle($p, $admin)->berita_acara;

    expect($s['periode'])->toMatchArray(['nama' => 'Sensus 2026', 'jenis' => 'sensus', 'mulai' => '2026-11-01'])
        ->and($s['kop']['kota_surat'])->toBe('Tasikmalaya')
        ->and($s['total'])->toMatchArray(['ruangan' => 2, 'total' => 4, 'ditemukan' => 2, 'kondisi_berubah' => 1, 'tidak_ditemukan' => 1, 'berlebih' => 1]);

    $r1 = collect($s['ruangan'])->firstWhere('kode', 'R-1');
    expect($r1)->toMatchArray(['total' => 3, 'ditemukan' => 1, 'kondisi_berubah' => 1, 'tidak_ditemukan' => 1, 'berlebih' => 1])
        ->and($s['tidak_ditemukan'])->toHaveCount(1)->and($s['tidak_ditemukan'][0])->toMatchArray(['nama' => 'Aset C', 'nup' => 3, 'ruangan' => 'Ruang R-1'])
        ->and($s['kondisi_berubah'][0])->toMatchArray(['nama' => 'Aset B', 'kondisi_data' => 'B', 'kondisi_ditemukan' => 'RR'])
        ->and($s['berlebih'][0])->toMatchArray(['deskripsi' => 'Lemari tanpa label'])
        ->and($s['penandatangan']['penutup'])->toMatchArray(['nama' => 'Admin BMN', 'nip' => '198501012010011001'])
        ->and($s['penandatangan']['pejabat'])->toBeNull();
});

it('menolak penutupan bila ada ruangan yang belum selesai, dan di luar status berjalan', function () {
    ['p' => $p, 'admin' => $admin, 'ruangan' => [$r1, $r2]] = baSkenario(selesai: false);

    expect(fn () => app(TutupPeriode::class)->handle($p, $admin))->toThrow(ValidationException::class, 'Ruang R-1');
    expect($p->fresh()->status)->toBe(StatusPeriodeInventarisasi::Berjalan);

    $rencana = app(BuatPeriode::class)->handle('Lain', JenisInventarisasi::OpnameInternal, Carbon::parse('2027-01-01'), null, [$r1->id], $admin);
    expect(fn () => app(TutupPeriode::class)->handle($rencana, $admin))->toThrow(ValidationException::class, 'hanya periode berjalan');
});

it('hanya admin yang menutup periode', function () {
    ['p' => $p, 'pejabat' => $pejabat, 'picR1' => $pic] = baSkenario();

    foreach ([$pejabat, $pic, baUser('pimpinan'), baUser('civitas')] as $u) {
        expect(fn () => app(TutupPeriode::class)->handle($p, $u))->toThrow(AuthorizationException::class);
    }
});

it('menutup dua kali ditolak dan aset hilang yang ditemukan kembali menjadi aktif', function () {
    ['p' => $p, 'admin' => $admin] = baSkenario(selesai: false);
    $r = Ruangan::query()->where('kode', 'R-1')->first();
    $hilang = Aset::factory()->diRuangan($r)->create(['nama' => 'Dulu Hilang', 'status' => 'hilang']);
    foreach ($p->ruangan as $inv) {
        $pemindai = baUser('admin-bmn');
        if ($inv->ruangan_id === $r->id) {
            app(CatatHasilPindai::class)->handle($inv, $hilang, $pemindai);
        }
        app(SelesaikanInventarisasiRuangan::class)->handle($inv->fresh(), $pemindai);
    }

    app(TutupPeriode::class)->handle($p, $admin);

    expect($hilang->fresh()->status)->toBe(StatusAset::Aktif)->and($hilang->riwayatStatus()->first()->catatan)->toContain('Ditemukan kembali');
    expect(fn () => app(TutupPeriode::class)->handle($p, $admin))->toThrow(ValidationException::class);
});

it('verifikasi admin: tidak ditemukan → hilang dengan catatan (BR-14)', function () {
    ['p' => $p, 'admin' => $admin, 'aset' => $aset, 'picR1' => $pic] = baSkenario();
    $hasil = HasilInventarisasi::query()->where('aset_id', $aset['c']->id)->first();

    expect(fn () => app(TetapkanAsetHilang::class)->handle($hasil, $admin, 'x'))->toThrow(ValidationException::class, 'setelah periode ditutup');

    app(TutupPeriode::class)->handle($p, $admin);

    expect(fn () => app(TetapkanAsetHilang::class)->handle($hasil, $admin, ' '))->toThrow(ValidationException::class)
        ->and(fn () => app(TetapkanAsetHilang::class)->handle($hasil, $pic, 'x'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TetapkanAsetHilang::class)->handle(HasilInventarisasi::query()->where('aset_id', $aset['a']->id)->first(), $admin, 'x'))->toThrow(ValidationException::class);

    app(TetapkanAsetHilang::class)->handle($hasil, $admin, 'Sudah dicari ke seluruh gedung');
    app(TetapkanAsetHilang::class)->handle($hasil, $admin, 'diulang');   // idempoten

    expect($aset['c']->fresh()->status)->toBe(StatusAset::Hilang)
        ->and($aset['c']->riwayatStatus()->first())->ke->toBe('hilang')->dari->toBe('aktif')->oleh->toBe($admin->id)
        ->and($aset['c']->riwayatStatus()->first()->catatan)->toContain('Sudah dicari ke seluruh gedung')->toContain('Sensus 2026');
});

it('pejabat mengesahkan berita acara; admin dan periode berjalan tidak', function () {
    ['p' => $p, 'admin' => $admin, 'pejabat' => $pejabat] = baSkenario();

    expect(fn () => app(SahkanBeritaAcara::class)->handle($p, $pejabat))->toThrow(AuthorizationException::class);   // masih berjalan (policy)

    app(TutupPeriode::class)->handle($p, $admin);
    expect(fn () => app(SahkanBeritaAcara::class)->handle($p->fresh(), $admin))->toThrow(AuthorizationException::class);

    $sah = app(SahkanBeritaAcara::class)->handle($p->fresh(), $pejabat);

    expect($sah)->status->toBe(StatusPeriodeInventarisasi::Disahkan)->disahkan_oleh->toBe($pejabat->id)->and($sah->disahkan_pada)->not->toBeNull()
        ->and($sah->berita_acara['penandatangan']['pejabat'])->toMatchArray(['nama' => 'Pak Pejabat', 'nip' => '197001011995031001']);
    expect(fn () => app(SahkanBeritaAcara::class)->handle($sah->fresh(), $pejabat))->toThrow(AuthorizationException::class);
});

it('PDF berita acara dirender dari snapshot dan tidak berubah setelah data aset berubah', function () {
    ['p' => $p, 'admin' => $admin, 'pejabat' => $pejabat, 'aset' => $aset] = baSkenario();
    $p = app(TutupPeriode::class)->handle($p, $admin);
    $p = app(SahkanBeritaAcara::class)->handle($p, $pejabat);
    $render = fn () => view('pdf.berita-acara', ['periode' => $p->fresh(), 's' => $p->fresh()->berita_acara])->render();
    $sebelum = $render();

    $aset['c']->update(['nama' => 'Nama Baru Aset C']);

    expect($render())->toBe($sebelum)
        ->and($sebelum)->toContain('Aset C')->toContain('Lemari tanpa label')->toContain('Aset B')->toContain('Sensus 2026')
        ->toContain('Admin BMN')->toContain('Pak Pejabat')->toContain('Berita Acara Hasil Inventarisasi')
        ->not->toContain('Nama Baru Aset C');
});

it('endpoint PDF dan Excel: hanya untuk periode ditutup/disahkan dan pengguna berhak', function () {
    ['p' => $p, 'admin' => $admin, 'pejabat' => $pejabat, 'picR1' => $picR1, 'ruangan' => [, $r2]] = baSkenario();

    $this->get(route('cetak.berita-acara', $p))->assertRedirect('/login');                         // tamu
    $this->actingAs($admin)->get(route('cetak.berita-acara', $p))->assertForbidden();               // masih berjalan
    $this->actingAs($admin)->get(route('ekspor.inventarisasi.selisih', $p))->assertForbidden();

    app(TutupPeriode::class)->handle($p, $admin);

    $pdf = $this->actingAs($admin)->get(route('cetak.berita-acara', $p));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf')->and($pdf->getContent())->toStartWith('%PDF');

    $this->actingAs($pejabat)->get(route('cetak.berita-acara', $p))->assertOk();
    $this->actingAs($picR1)->get(route('cetak.berita-acara', $p))->assertOk();
    $this->actingAs(baUser('pimpinan'))->get(route('cetak.berita-acara', $p))->assertOk();
    $this->actingAs(baUser('pic-ruangan'))->get(route('cetak.berita-acara', $p))->assertForbidden();   // PIC tanpa ruangan terkait
    $this->actingAs(baUser('civitas'))->get(route('cetak.berita-acara', $p))->assertForbidden();
});

it('Excel selisih memuat tidak ditemukan, kondisi berubah, dan berlebih dari snapshot', function () {
    ['p' => $p, 'admin' => $admin] = baSkenario();
    app(TutupPeriode::class)->handle($p, $admin);

    $respons = $this->actingAs($admin)->get(route('ekspor.inventarisasi.selisih', $p));
    $respons->assertOk();
    expect($respons->headers->get('content-type'))->toContain('spreadsheetml');

    $berkas = sys_get_temp_dir().'/selisih-'.bin2hex(random_bytes(4)).'.xlsx';
    copy($respons->baseResponse->getFile()->getPathname(), $berkas);
    $baris = SimpleExcelReader::create($berkas, 'xlsx')->getRows()->all();
    @unlink($berkas);

    $perHasil = collect($baris)->groupBy('Hasil');
    expect($perHasil->keys()->sort()->values()->all())->toBe(['Berlebih (tidak tercatat)', 'Kondisi berubah', 'Tidak ditemukan'])
        ->and($perHasil['Tidak ditemukan'][0])->toMatchArray(['Nama' => 'Aset C', 'Ruangan' => 'Ruang R-1', 'NUP' => 3])
        ->and($perHasil['Kondisi berubah'][0])->toMatchArray(['Nama' => 'Aset B', 'Kondisi data' => 'B', 'Kondisi ditemukan' => 'RR'])
        ->and($perHasil['Berlebih (tidak tercatat)'][0])->toMatchArray(['Keterangan' => 'Lemari tanpa label']);
});

it('peringatan BR-15: tanpa inventarisasi disahkan → bahaya; di bawah ambang → tidak ada; di atas ambang → peringatan; lewat 5 tahun → bahaya', function () {
    expect(PeringatanInventarisasi::cek())->toMatchArray(['tingkat' => 'danger', 'terakhir' => null]);

    $p = PeriodeInventarisasi::query()->create(['nama' => 'Lama', 'mulai' => '2020-01-01', 'status' => StatusPeriodeInventarisasi::Disahkan, 'disahkan_pada' => now()->subYears(2)]);
    expect(PeringatanInventarisasi::cek())->toBeNull();

    $p->update(['disahkan_pada' => now()->subYears(4)->subMonths(2)]);
    expect(PeringatanInventarisasi::cek())->tingkat->toBe('warning')->pesan->toContain('4 tahun lalu')->toContain('Segera jadwalkan');

    $p->update(['disahkan_pada' => now()->subYears(5)->subDay()]);
    expect(PeringatanInventarisasi::cek())->tingkat->toBe('danger')->pesan->toContain('Batas aturan 5 tahun terlampaui');

    Pengaturan::simpan('ambang_pengingat_inventarisasi_tahun', 3);
    $p->update(['disahkan_pada' => now()->subYears(3)->subDay()]);
    expect(PeringatanInventarisasi::cek())->tingkat->toBe('warning');
});

it('peringatan hanya memakai periode yang disahkan (ditutup/berjalan tidak dihitung)', function () {
    PeriodeInventarisasi::query()->create(['nama' => 'Belum sah', 'mulai' => '2026-01-01', 'status' => StatusPeriodeInventarisasi::Ditutup, 'ditutup_pada' => now()]);

    expect(PeringatanInventarisasi::cek())->toMatchArray(['tingkat' => 'danger', 'terakhir' => null]);
});

it('widget peringatan tampil di dasbor hanya untuk pengelola, pejabat, dan pimpinan', function () {
    foreach (['admin-bmn' => true, 'pejabat-penatausahaan' => true, 'pimpinan' => true, 'super-admin' => true, 'pic-ruangan' => false] as $peran => $boleh) {
        $this->actingAs(baUser($peran));
        expect(PeringatanInventarisasiWidget::canView())->toBe($boleh);
    }

    $this->actingAs(baUser('admin-bmn'));
    Livewire::test(PeringatanInventarisasiWidget::class)->assertSee('Pengingat inventarisasi')->assertSee('Belum ada inventarisasi yang disahkan');
});

it('panel: tutup lalu sahkan lewat aksi halaman periode; verifikasi hilang lewat relation manager', function () {
    ['p' => $p, 'admin' => $admin, 'pejabat' => $pejabat, 'aset' => $aset] = baSkenario();

    $this->actingAs($admin);
    Livewire::test(LihatPeriodeInventarisasi::class, ['record' => $p->getRouteKey()])
        ->assertActionVisible('tutup')->assertActionHidden('sahkan')->assertActionHidden('cetakBeritaAcara')
        ->callAction('tutup');
    expect($p->fresh()->status)->toBe(StatusPeriodeInventarisasi::Ditutup);

    Livewire::test(LihatPeriodeInventarisasi::class, ['record' => $p->getRouteKey()])
        ->assertActionHidden('tutup')->assertActionVisible('cetakBeritaAcara')->assertActionVisible('unduhSelisih');

    $hasil = HasilInventarisasi::query()->where('aset_id', $aset['c']->id)->first();
    Livewire::test(TidakDitemukanRelationManager::class, ['ownerRecord' => $p->fresh(), 'pageClass' => LihatPeriodeInventarisasi::class])
        ->assertCanSeeTableRecords([$hasil])
        ->callAction(TestAction::make('tetapkanHilang')->table($hasil), ['catatan' => 'Tidak ada di seluruh gedung']);
    expect($aset['c']->fresh()->status)->toBe(StatusAset::Hilang);

    $this->actingAs($pejabat);
    Livewire::test(LihatPeriodeInventarisasi::class, ['record' => $p->getRouteKey()])
        ->assertActionVisible('sahkan')->assertActionHidden('tutup')->callAction('sahkan');
    expect($p->fresh()->status)->toBe(StatusPeriodeInventarisasi::Disahkan);
});
