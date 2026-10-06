<?php

use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusBmn;
use App\Models\Aset;
use App\Models\Ruangan;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(PeranDanIzinSeeder::class));

function ruang(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

function picDi(Ruangan ...$ruangan): User
{
    $pic = User::factory()->create();
    $pic->assignRole('pic-ruangan');
    $pic->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));

    return $pic;
}

it('menyimpan aset dengan UUIDv7, enum, dan nilai DECIMAL(15,2)', function () {
    $aset = Aset::factory()->create(['nilai_perolehan' => '1234567.5']);

    expect($aset->id[14])->toBe('7')
        ->and($aset->fresh()->nilai_perolehan)->toBe('1234567.50')
        ->and($aset->status)->toBe(StatusAset::Aktif)
        ->and($aset->kondisi)->toBe(KondisiAset::Baik)
        ->and($aset->status_bmn)->toBe(StatusBmn::Tercatat);
});

it('menjaga kombinasi kode_barang + NUP unik (BR-01)', function () {
    $r = ruang('R-1');
    Aset::factory()->diRuangan($r)->create(['kode_barang' => '3100102001', 'nup' => 15]);

    expect(fn () => Aset::factory()->diRuangan($r)->create(['kode_barang' => '3100102001', 'nup' => 15]))
        ->toThrow(QueryException::class);

    // kode sama dengan NUP berbeda, atau NUP sama dengan kode berbeda, diperbolehkan
    Aset::factory()->diRuangan($r)->create(['kode_barang' => '3100102001', 'nup' => 16]);
    Aset::factory()->diRuangan($r)->create(['kode_barang' => '3100102002', 'nup' => 15]);

    expect(Aset::count())->toBe(3);
});

it('mewajibkan kode+NUP untuk barang tercatat dan kode internal unik untuk barang belum tercatat (BR-01)', function () {
    $r = ruang('R-1');

    expect(fn () => Aset::factory()->diRuangan($r)->create(['kode_barang' => null, 'nup' => null]))
        ->toThrow(ValidationException::class);
    expect(fn () => Aset::factory()->diRuangan($r)->belumTercatat('')->create())
        ->toThrow(ValidationException::class);

    Aset::factory()->diRuangan($r)->belumTercatat('INT-001')->create();
    expect(fn () => Aset::factory()->diRuangan($r)->belumTercatat('INT-001')->create())
        ->toThrow(QueryException::class);
});

it('mewajibkan tepat satu lokasi: ruangan atau lokasi lainnya (BR-02)', function () {
    $r = ruang('R-1');

    expect(fn () => Aset::factory()->create(['ruangan_id' => null, 'lokasi_lainnya' => null]))
        ->toThrow(ValidationException::class)
        ->and(fn () => Aset::factory()->diRuangan($r)->create(['lokasi_lainnya' => 'Gudang luar']))
        ->toThrow(ValidationException::class);

    $dbl = Aset::factory()->create(['ruangan_id' => null, 'lokasi_lainnya' => 'Gudang luar']);
    expect($dbl->ruangan_id)->toBeNull();
});

it('menolak status dihapus tanpa nomor dan tanggal SK (BR-04)', function () {
    $aset = Aset::factory()->create(['status' => StatusAset::DiusulkanHapus]);

    expect(fn () => $aset->update(['status' => StatusAset::Dihapus]))->toThrow(ValidationException::class);
    expect(fn () => $aset->update(['status' => StatusAset::Dihapus, 'nomor_sk_penghapusan' => 'SK-1/2026']))->toThrow(ValidationException::class);

    $aset->update(['status' => StatusAset::Dihapus, 'nomor_sk_penghapusan' => 'SK-1/2026', 'tanggal_sk_penghapusan' => '2026-09-01']);
    expect($aset->fresh()->status)->toBe(StatusAset::Dihapus);
});

it('menolak kondisi di luar B/RR/RB', function () {
    expect(fn () => Aset::factory()->create(['kondisi' => 'XX']))->toThrow(ValueError::class);
});

it('scope diRuangan dan dapatDipinjam menerapkan BR-11', function () {
    $r = ruang('R-1');
    $lain = ruang('R-2');
    Aset::factory()->diRuangan($r)->dapatDipinjam()->create(['nama' => 'OK']);
    Aset::factory()->diRuangan($r)->dapatDipinjam()->create(['nama' => 'RB', 'kondisi' => 'RB']);
    Aset::factory()->diRuangan($r)->dapatDipinjam()->create(['nama' => 'Perbaikan', 'status' => 'dalam_perbaikan']);
    Aset::factory()->diRuangan($r)->dapatDipinjam()->create(['nama' => 'Hilang', 'status' => 'hilang']);
    Aset::factory()->diRuangan($r)->create(['nama' => 'Tidak ditandai']);
    Aset::factory()->diRuangan($lain)->dapatDipinjam()->create(['nama' => 'Ruang lain']);

    expect(Aset::query()->diRuangan($r)->count())->toBe(5)
        ->and(Aset::query()->diRuangan($r->id)->dapatDipinjam()->pluck('nama')->all())->toBe(['OK'])
        ->and(Aset::query()->dapatDipinjam()->count())->toBe(2)
        ->and(Aset::query()->where('nama', 'RB')->first()->memenuhiSyaratPinjam())->toBeFalse();
});

it('sedangDipinjam dihitung, tidak disimpan (BR-04) dan salah tanpa peminjaman', function () {
    $aset = Aset::factory()->create();

    expect($aset->sedangDipinjam)->toBeFalse()
        ->and(Schema::hasColumn('aset', 'sedang_dipinjam'))->toBeFalse()
        ->and(Schema::hasColumn('aset', 'dipinjam'))->toBeFalse();
});

it('kodeTampil memakai kode barang+NUP atau kode internal', function () {
    $a = Aset::factory()->create(['kode_barang' => '3100102001', 'nup' => 7]);
    $b = Aset::factory()->belumTercatat('INT-9')->create();

    expect($a->kode_tampil)->toBe('3100102001 / 7')->and($b->kode_tampil)->toBe('INT-9');
});

it('PIC hanya dapat mengubah kondisi dan mencetak label aset di ruangannya (BR-05)', function () {
    [$r1, $r2] = [ruang('R-1'), ruang('R-2')];
    $pic = picDi($r1);
    $milikPic = Aset::factory()->diRuangan($r1)->create();
    $milikLain = Aset::factory()->diRuangan($r2)->create();

    expect($pic->can('ubahKondisi', $milikPic))->toBeTrue()
        ->and($pic->can('ubahKondisi', $milikLain))->toBeFalse()
        ->and($pic->can('cetakLabel', $milikPic))->toBeTrue()
        ->and($pic->can('cetakLabel', $milikLain))->toBeFalse()
        ->and($pic->can('view', $milikLain))->toBeTrue()   // PIC boleh melihat semua aset
        ->and($pic->can('update', $milikPic))->toBeFalse()   // data induk hanya admin
        ->and($pic->can('create', Aset::class))->toBeFalse();
});

it('admin mengelola semua ruangan; peran lain tidak', function (string $peran, bool $ubahKondisi, bool $ubahData) {
    $aset = Aset::factory()->create();
    $user = User::factory()->create();
    $user->assignRole($peran);

    expect($user->can('ubahKondisi', $aset))->toBe($ubahKondisi)
        ->and($user->can('update', $aset))->toBe($ubahData);
})->with([
    ['admin-bmn', true, true],
    ['super-admin', true, true],
    ['pimpinan', false, false],
    ['pejabat-penatausahaan', false, false],
    ['civitas', false, false],
]);

it('tidak pernah mengizinkan penghapusan aset kecuali super-admin', function () {
    $aset = Aset::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('admin-bmn');
    $super = User::factory()->create();
    $super->assignRole('super-admin');

    expect($admin->can('delete', $aset))->toBeFalse()
        ->and($admin->can('forceDelete', $aset))->toBeFalse()
        ->and($super->can('delete', $aset))->toBeTrue();
});

it('mencatat perubahan aset ke jejak audit', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $aset = Aset::factory()->create();
    $aset->update(['merk_tipe' => 'Merk Baru']);

    $log = DB::table('activity_log')->where('subject_id', $aset->id)->where('description', 'updated')->first();

    expect($log->causer_id)->toBe($user->id)->and($log->log_name)->toBe('aset');
});

it('StatusAset menerapkan transisi sah PRD §7', function (StatusAset $dari, StatusAset $ke, bool $sah) {
    expect($dari->dapatBerpindahKe($ke))->toBe($sah);
})->with([
    [StatusAset::Aktif, StatusAset::DalamPerbaikan, true],
    [StatusAset::DalamPerbaikan, StatusAset::Aktif, true],
    [StatusAset::Aktif, StatusAset::Hilang, true],
    [StatusAset::Hilang, StatusAset::Aktif, true],
    [StatusAset::Hilang, StatusAset::DiusulkanHapus, true],
    [StatusAset::Aktif, StatusAset::DiusulkanHapus, true],
    [StatusAset::DiusulkanHapus, StatusAset::Dihapus, true],
    [StatusAset::DiusulkanHapus, StatusAset::Aktif, true],
    [StatusAset::Aktif, StatusAset::Dihapus, false],
    [StatusAset::DalamPerbaikan, StatusAset::Hilang, false],
    [StatusAset::Dihapus, StatusAset::Aktif, false],
]);
