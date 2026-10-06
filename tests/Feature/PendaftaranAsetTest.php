<?php

use App\Actions\Aset\DaftarkanAset;
use App\Actions\Aset\DaftarkanAsetMassal;
use App\Actions\Aset\UbahDataAset;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Exceptions\FiturNonaktif;
use App\Filament\Resources\Aset\Pages\BuatAset;
use App\Filament\Resources\Aset\Pages\DaftarAset;
use App\Filament\Resources\Aset\Pages\UbahAset;
use App\Models\Aset;
use App\Models\KodefikasiBarang;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\Pengaturan;
use Database\Seeders\KodefikasiBarangSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    $this->seed(KodefikasiBarangSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function petugas(string $peran): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    return $user;
}

function dataKursi(Ruangan $r, array $tambahan = []): array
{
    return [
        'status_bmn' => 'tercatat', 'kode_barang' => '3100102002', 'nama' => 'Kursi Dosen', 'merk_tipe' => 'Chitose',
        'tahun_perolehan' => 2024, 'tanggal_perolehan' => '2024-03-01', 'nilai_perolehan' => '1250000.50',
        'sumber_perolehan' => 'pembelian', 'ruangan_id' => $r->id, 'kondisi' => 'B', ...$tambahan,
    ];
}

function ruangUji(string $kode = 'R-1'): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

it('mendaftarkan satu aset beserta riwayat awal dan nilai DECIMAL', function () {
    $admin = petugas('admin-bmn');
    $aset = app(DaftarkanAset::class)->handle(dataKursi(ruangUji(), ['nup' => 1]), $admin);

    expect($aset->fresh()->nilai_perolehan)->toBe('1250000.50')
        ->and($aset->riwayatKondisi()->count())->toBe(1)
        ->and($aset->riwayatKondisi()->first())->ke->toBe('B')->dari->toBeNull()->oleh->toBe($admin->id)
        ->and($aset->riwayatLokasi()->first()->ke_ruangan_id)->toBe($aset->ruangan_id)
        ->and($aset->riwayatStatus()->first()->ke)->toBe('aktif');
});

it('tidak membiarkan input menentukan status atau SK penghapusan saat mendaftar', function () {
    $aset = app(DaftarkanAset::class)->handle(dataKursi(ruangUji(), ['nup' => 1, 'status' => 'dihapus', 'nomor_sk_penghapusan' => 'X']), petugas('admin-bmn'));

    expect($aset->status)->toBe(StatusAset::Aktif)->and($aset->nomor_sk_penghapusan)->toBeNull();
});

it('menolak pendaftaran oleh peran tanpa aset.kelola', function (string $peran) {
    expect(fn () => app(DaftarkanAset::class)->handle(dataKursi(ruangUji(), ['nup' => 1]), petugas($peran)))
        ->toThrow(AuthorizationException::class);
})->with(['pic-ruangan', 'pimpinan', 'pejabat-penatausahaan', 'civitas']);

it('mendaftarkan massal 20 kursi: 20 baris, NUP berurutan, satu kelompok pengadaan (US-AST-02)', function () {
    $hasil = app(DaftarkanAsetMassal::class)->handle(dataKursi(ruangUji()), 20, 101, petugas('admin-bmn'));

    expect($hasil)->toHaveCount(20)
        ->and(Aset::count())->toBe(20)
        ->and(Aset::query()->orderBy('nup')->pluck('nup')->all())->toBe(range(101, 120))
        ->and(Aset::query()->pluck('kelompok_pengadaan')->unique()->count())->toBe(1)
        ->and(Aset::query()->whereNotNull('kelompok_pengadaan')->count())->toBe(20)
        ->and(DB::table('riwayat_kondisi_aset')->count())->toBe(20);
});

it('massal tanpa NUP awal melanjutkan NUP terakhir kode barang yang sama', function () {
    $r = ruangUji();
    $admin = petugas('admin-bmn');
    app(DaftarkanAsetMassal::class)->handle(dataKursi($r), 3, null, $admin);
    app(DaftarkanAsetMassal::class)->handle(dataKursi($r), 2, null, $admin);

    expect(Aset::query()->orderBy('nup')->pluck('nup')->all())->toBe([1, 2, 3, 4, 5]);
});

it('massal bersifat semua-atau-tidak-sama-sekali bila NUP bentrok', function () {
    $r = ruangUji();
    $admin = petugas('admin-bmn');
    app(DaftarkanAset::class)->handle(dataKursi($r, ['nup' => 105]), $admin);

    expect(fn () => app(DaftarkanAsetMassal::class)->handle(dataKursi($r), 10, 101, $admin))
        ->toThrow(ValidationException::class);
    expect(Aset::count())->toBe(1);
});

it('massal belum tercatat memakai awalan kode internal unik', function () {
    $hasil = app(DaftarkanAsetMassal::class)->handle(
        dataKursi(ruangUji(), ['status_bmn' => 'belum_tercatat', 'kode_barang' => null]), 3, null, petugas('admin-bmn'), 'HIB-2026',
    );

    expect($hasil->pluck('kode_internal')->all())->toBe(['HIB-2026-001', 'HIB-2026-002', 'HIB-2026-003'])
        ->and($hasil->pluck('nup')->filter()->all())->toBe([]);

    expect(fn () => app(DaftarkanAsetMassal::class)->handle(dataKursi(ruangUji('R-2'), ['status_bmn' => 'belum_tercatat', 'kode_barang' => null]), 2, null, petugas('admin-bmn')))
        ->toThrow(ValidationException::class);
});

it('menolak jumlah massal di luar 1..500', function (int $jumlah) {
    expect(fn () => app(DaftarkanAsetMassal::class)->handle(dataKursi(ruangUji()), $jumlah, null, petugas('admin-bmn')))
        ->toThrow(ValidationException::class);
})->with([0, 501]);

it('toggle fitur tambah aset dibaca di Action: dimatikan → ditolak (BR-22)', function () {
    Pengaturan::simpan('fitur_tambah_aset', false);
    $admin = petugas('admin-bmn');

    expect(fn () => app(DaftarkanAset::class)->handle(dataKursi(ruangUji(), ['nup' => 1]), $admin))->toThrow(FiturNonaktif::class)
        ->and(fn () => app(DaftarkanAsetMassal::class)->handle(dataKursi(ruangUji('R-2')), 5, null, $admin))->toThrow(FiturNonaktif::class);
    expect(Aset::count())->toBe(0);
});

it('UbahDataAset hanya mengubah kolom deskriptif; identitas, kondisi, status, lokasi diabaikan', function () {
    $admin = petugas('admin-bmn');
    $r = ruangUji();
    $aset = app(DaftarkanAset::class)->handle(dataKursi($r, ['nup' => 7]), $admin);

    app(UbahDataAset::class)->handle($aset, [
        'nama' => 'Kursi Baru', 'nilai_perolehan' => '99.99', 'dapat_dipinjam' => true,
        'kode_barang' => '3100102001', 'nup' => 999, 'kondisi' => 'RB', 'status' => 'hilang', 'ruangan_id' => ruangUji('R-9')->id,
    ], $admin);

    $aset->refresh();
    expect($aset->nama)->toBe('Kursi Baru')->and($aset->nilai_perolehan)->toBe('99.99')->and($aset->dapat_dipinjam)->toBeTrue()
        ->and($aset->kode_barang)->toBe('3100102002')->and($aset->nup)->toBe(7)
        ->and($aset->kondisi)->toBe(KondisiAset::Baik)->and($aset->status)->toBe(StatusAset::Aktif)->and($aset->ruangan_id)->toBe($r->id);

    expect(fn () => app(UbahDataAset::class)->handle($aset, ['nama' => 'X'], petugas('pic-ruangan')))->toThrow(AuthorizationException::class);
});

it('halaman pendaftaran Filament mendaftarkan aset lewat Action', function () {
    $this->actingAs(petugas('admin-bmn'));
    $r = ruangUji();

    Livewire::test(BuatAset::class)
        ->fillForm(dataKursi($r, ['nup' => 3, 'nilai_perolehan' => 500000]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Aset::query()->where('nup', 3)->first())->nama->toBe('Kursi Dosen');
});

it('form menolak kode barang yang bukan sub-sub kelompok dan kode+NUP ganda', function () {
    $this->actingAs(petugas('admin-bmn'));
    $r = ruangUji();

    Livewire::test(BuatAset::class)
        ->fillForm(dataKursi($r, ['nup' => 3, 'kode_barang' => '3100102']))
        ->call('create')
        ->assertHasFormErrors(['kode_barang']);

    expect(KodefikasiBarang::query()->where('kode', '3100102')->value('tingkat'))->toBe(4);
});

it('halaman ubah menonaktifkan kolom identitas dan menyimpan perubahan deskriptif', function () {
    $admin = petugas('admin-bmn');
    $this->actingAs($admin);
    $aset = app(DaftarkanAset::class)->handle(dataKursi(ruangUji(), ['nup' => 1]), $admin);

    Livewire::test(UbahAset::class, ['record' => $aset->getRouteKey()])
        ->assertFormFieldDisabled('nup')
        ->assertFormFieldDisabled('kode_barang')
        ->fillForm(['nama' => 'Kursi Direvisi'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($aset->refresh()->nama)->toBe('Kursi Direvisi')->and($aset->nup)->toBe(1);
});

it('aksi daftarkan massal di daftar aset membuat banyak baris', function () {
    $this->actingAs(petugas('admin-bmn'));
    $r = ruangUji();

    Livewire::test(DaftarAset::class)
        ->callAction('daftarkanMassal', [...dataKursi($r), 'jumlah' => 4, 'nup_awal' => 10])
        ->assertHasNoFormErrors();

    expect(Aset::query()->orderBy('nup')->pluck('nup')->all())->toBe([10, 11, 12, 13]);
});

it('PIC dapat melihat daftar aset tetapi tidak mendaftar atau mengubah', function () {
    $this->actingAs(petugas('pic-ruangan'));
    $aset = Aset::factory()->create();

    $this->get('/admin/aset')->assertOk();
    $this->get('/admin/aset/create')->assertForbidden();
    $this->get('/admin/aset/'.$aset->id.'/edit')->assertForbidden();
});

it('daftar aset menyaring dan mencari', function () {
    $this->actingAs(petugas('admin-bmn'));
    $r1 = ruangUji('R-1');
    $r2 = ruangUji('R-2');
    $a = Aset::factory()->diRuangan($r1)->create(['nama' => 'Proyektor Epson', 'merk_tipe' => 'Generik', 'kondisi' => 'RB']);
    $b = Aset::factory()->diRuangan($r2)->create(['nama' => 'Meja Lipat', 'merk_tipe' => 'Generik', 'label_perlu_cetak_ulang' => true]);

    Livewire::test(DaftarAset::class)
        ->searchTable('Epson')->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b])
        ->searchTable('')
        ->filterTable('ruangan_id', $r2->id)->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a])
        ->removeTableFilters()
        ->filterTable('kondisi', 'RB')->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b])
        ->removeTableFilters()
        ->filterTable('label_perlu_cetak_ulang', true)->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
});
