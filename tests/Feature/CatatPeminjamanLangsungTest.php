<?php

use App\Actions\Peminjaman\CatatPeminjamanLangsung;
use App\Enums\KondisiAset;
use App\Enums\StatusPeminjaman;
use App\Exceptions\FiturNonaktif;
use App\Livewire\Keranjang;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\Pengaturan;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    config(['aset.lock_tunggu_detik' => 0]);
});

function orangPjm(string $peran, array $ruangan = []): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));

    return $user;
}

function ruangPjm(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

function asetPinjam(Ruangan $r, array $atribut = []): Aset
{
    return Aset::factory()->diRuangan($r)->dapatDipinjam()->create($atribut);
}

function catat(array $ids, User $pic, array $peminjam = ['nama_peminjam' => 'Budi'], ?DateTimeInterface $kembali = null, string $keperluan = 'Kuliah')
{
    return app(CatatPeminjamanLangsung::class)->handle($ids, $peminjam, $keperluan, $kembali ? Carbon\Carbon::instance($kembali) : now()->addDays(2), $pic);
}

it('mencatat peminjaman langsung beberapa aset: status dipinjam, nomor, kondisi saat pinjam (US-PJM-01)', function () {
    $r = ruangPjm('R-1');
    $pic = orangPjm('pic-ruangan', [$r]);
    $civitas = orangPjm('civitas');
    $civitas->update(['name' => 'Dr. Siti', 'no_hp' => '0811']);
    [$a, $b] = [asetPinjam($r), asetPinjam($r, ['kondisi' => 'RR'])];

    $p = catat([$a->id, $b->id], $pic, ['peminjam_user_id' => $civitas->id, 'unit_peminjam' => 'Prodi Matematika']);

    expect($p->nomor)->toBe('PJM-'.now()->year.'-00001')
        ->and($p->status)->toBe(StatusPeminjaman::Dipinjam)
        ->and($p)->peminjam_user_id->toBe($civitas->id)->nama_peminjam->toBe('Dr. Siti')->kontak_peminjam->toBe('0811')->unit_peminjam->toBe('Prodi Matematika')
        ->and($p->dicatat_oleh)->toBe($pic->id)->and($p->diserahkan_oleh)->toBe($pic->id)->and($p->diserahkan_pada)->not->toBeNull()
        ->and($p->item)->toHaveCount(2)
        ->and($p->item->firstWhere('aset_id', $b->id)->kondisi_saat_pinjam)->toBe(KondisiAset::RusakRingan)
        ->and($a->fresh()->sedangDipinjam)->toBeTrue();

    expect(catat([asetPinjam($r)->id], $pic)->nomor)->toBe('PJM-'.now()->year.'-00002');
});

it('dua PIC meminjamkan aset yang sama bersamaan → hanya satu berhasil (BR-08)', function () {
    $r = ruangPjm('R-1');
    [$pic1, $pic2] = [orangPjm('pic-ruangan', [$r]), orangPjm('pic-ruangan', [$r])];
    $aset = asetPinjam($r);

    catat([$aset->id], $pic1, ['nama_peminjam' => 'Pertama']);

    expect(fn () => catat([$aset->id], $pic2, ['nama_peminjam' => 'Kedua']))->toThrow(ValidationException::class, 'tidak tersedia');
    expect(Peminjaman::count())->toBe(1)->and(Peminjaman::first()->nama_peminjam)->toBe('Pertama');
});

it('lock Redis per aset yang sedang dipegang proses lain membuat pencatatan gagal rapi', function () {
    $r = ruangPjm('R-1');
    $pic = orangPjm('pic-ruangan', [$r]);
    $aset = asetPinjam($r);

    $lockProsesLain = Cache::lock("aset:pinjam:{$aset->id}", 10);
    expect($lockProsesLain->get())->toBeTrue();

    expect(fn () => catat([$aset->id], $pic))->toThrow(ValidationException::class, 'sedang diproses pengguna lain');
    expect(Peminjaman::count())->toBe(0);

    $lockProsesLain->release();
    expect(catat([$aset->id], $pic)->status)->toBe(StatusPeminjaman::Dipinjam);
});

it('keranjang semua-atau-tidak-sama-sekali: satu aset tidak tersedia menggagalkan semuanya (BR-08)', function () {
    $r = ruangPjm('R-1');
    $pic = orangPjm('pic-ruangan', [$r]);
    [$ok1, $ok2] = [asetPinjam($r), asetPinjam($r)];
    $rusak = asetPinjam($r, ['kondisi' => 'RB']);
    $diperbaiki = asetPinjam($r, ['status' => 'dalam_perbaikan']);

    try {
        catat([$ok1->id, $ok2->id, $rusak->id, $diperbaiki->id], $pic);
        $this->fail('Seharusnya gagal');
    } catch (ValidationException $e) {
        expect($e->errors()['aset'])->toHaveCount(2);
    }

    expect(Peminjaman::count())->toBe(0)->and(DB::table('peminjaman_item')->count())->toBe(0)
        ->and($ok1->fresh()->sedangDipinjam)->toBeFalse();
});

it('menolak aset yang sedang dipinjam peminjaman lain dan rentang tumpang tindih disetujui', function () {
    $r = ruangPjm('R-1');
    $pic = orangPjm('pic-ruangan', [$r]);
    $aset = asetPinjam($r);
    Peminjaman::factory()->status(StatusPeminjaman::Disetujui)->rentang(now()->addDay()->toDateTimeString(), now()->addDays(3)->toDateTimeString())->untuk($aset)->create();

    expect(fn () => catat([$aset->id], $pic, kembali: now()->addDays(2)))->toThrow(ValidationException::class);
    // kembali sebelum peminjaman yang disetujui itu dimulai → tersedia
    expect(catat([$aset->id], $pic, kembali: now()->addHours(5))->status)->toBe(StatusPeminjaman::Dipinjam);
});

it('PIC hanya dapat meminjamkan aset di ruangannya; PIC lain dan peran lain ditolak (BR-05)', function () {
    [$r1, $r2] = [ruangPjm('R-1'), ruangPjm('R-2')];
    $pic = orangPjm('pic-ruangan', [$r1]);
    $milik = asetPinjam($r1);
    $lain = asetPinjam($r2);

    expect(fn () => catat([$milik->id, $lain->id], $pic))->toThrow(AuthorizationException::class);
    expect(Peminjaman::count())->toBe(0);

    foreach (['pimpinan', 'pejabat-penatausahaan', 'civitas'] as $peran) {
        expect(fn () => catat([$milik->id], orangPjm($peran)))->toThrow(AuthorizationException::class);
    }

    // admin dapat meminjamkan dari ruangan mana pun
    expect(catat([$lain->id], orangPjm('admin-bmn'))->item)->toHaveCount(1);
});

it('membatasi rencana kembali dengan pengaturan maks_hari_pinjam', function () {
    $r = ruangPjm('R-1');
    $pic = orangPjm('pic-ruangan', [$r]);

    expect(fn () => catat([asetPinjam($r)->id], $pic, kembali: now()->addDays(15)))->toThrow(ValidationException::class, 'melebihi batas maksimal 14 hari');
    expect(catat([asetPinjam($r)->id], $pic, kembali: now()->addDays(14)->subMinute())->status)->toBe(StatusPeminjaman::Dipinjam);

    Pengaturan::simpan('maks_hari_pinjam', 3);
    expect(fn () => catat([asetPinjam($r)->id], $pic, kembali: now()->addDays(4)))->toThrow(ValidationException::class, 'melebihi batas maksimal 3 hari');
    expect(fn () => catat([asetPinjam($r)->id], $pic, kembali: now()->subHour()))->toThrow(ValidationException::class);
});

it('memvalidasi keperluan, keranjang, dan data peminjam', function () {
    $r = ruangPjm('R-1');
    $pic = orangPjm('pic-ruangan', [$r]);
    $aset = asetPinjam($r);

    expect(fn () => catat([$aset->id], $pic, keperluan: '  '))->toThrow(ValidationException::class)
        ->and(fn () => catat([], $pic))->toThrow(ValidationException::class)
        ->and(fn () => catat([$aset->id], $pic, ['nama_peminjam' => '']))->toThrow(ValidationException::class)
        ->and(fn () => catat([$aset->id], $pic, ['peminjam_user_id' => fake()->uuid()]))->toThrow(ValidationException::class)
        ->and(fn () => catat([$aset->id], $pic, ['peminjam_user_id' => User::factory()->create(['aktif' => false])->id]))->toThrow(ValidationException::class)
        ->and(fn () => catat([fake()->uuid()], $pic))->toThrow(ValidationException::class);
    expect(Peminjaman::count())->toBe(0);
});

it('selalu mencatat sebagai peminjam internal (pihak luar tidak diproses sebagai peminjaman, BR-07)', function () {
    $r = ruangPjm('R-1');
    $p = catat([asetPinjam($r)->id], orangPjm('pic-ruangan', [$r]), ['nama_peminjam' => 'Budi', 'jenis_peminjam' => 'pihak_luar']);

    expect($p->jenis_peminjam->value)->toBe('civitas');
});

it('toggle fitur peminjaman dimatikan menolak pencatatan (BR-22)', function () {
    $r = ruangPjm('R-1');
    Pengaturan::simpan('fitur_peminjaman', false);

    expect(fn () => catat([asetPinjam($r)->id], orangPjm('pic-ruangan', [$r])))->toThrow(FiturNonaktif::class);
});

it('mencatat peminjaman di jejak audit dengan pelaku dari autentikasi', function () {
    $r = ruangPjm('R-1');
    $pic = orangPjm('pic-ruangan', [$r]);
    $this->actingAs($pic);
    $p = catat([asetPinjam($r)->id], $pic);

    expect(DB::table('activity_log')->where('subject_id', $p->id)->where('log_name', 'peminjaman')->value('causer_id'))->toBe($pic->id);
});

it('/keranjang hanya untuk peminjaman.catat dan hanya mencari aset ruangan PIC', function () {
    [$r1, $r2] = [ruangPjm('R-1'), ruangPjm('R-2')];
    $pic = orangPjm('pic-ruangan', [$r1]);
    $milik = asetPinjam($r1, ['nama' => 'Proyektor Lab']);
    asetPinjam($r2, ['nama' => 'Proyektor Aula']);
    asetPinjam($r1, ['nama' => 'Proyektor Rusak', 'kondisi' => 'RB']);

    $this->get('/keranjang')->assertRedirect('/login');
    $this->actingAs(orangPjm('pimpinan'))->get('/keranjang')->assertForbidden();

    $this->actingAs($pic);
    $this->get('/keranjang')->assertOk()->assertSee('Keranjang peminjaman');

    Livewire::test(Keranjang::class)
        ->set('cari', 'Proyektor')
        ->assertSee('Proyektor Lab')->assertDontSee('Proyektor Aula')->assertDontSee('Proyektor Rusak')
        ->call('tambah', $milik->id)
        ->assertSet('daftar', [$milik->id])
        ->call('hapus', $milik->id)->assertSet('daftar', []);

    $asing = Aset::query()->where('nama', 'Proyektor Aula')->first();
    Livewire::test(Keranjang::class)->call('tambah', $asing->id)->assertSet('daftar', [])->assertSee('bukan di ruangan yang Anda kelola');
});

it('/keranjang menambah lewat teks label, mencatat peminjaman, dan menampilkan nomor', function () {
    $r = ruangPjm('R-1');
    $pic = orangPjm('pic-ruangan', [$r]);
    $aset = asetPinjam($r);
    $this->actingAs($pic);

    Livewire::test(Keranjang::class)
        ->set('teksPindai', url('/a/'.$aset->id))->call('tambahDariTeks')
        ->assertSet('daftar', [$aset->id])
        ->set('modePeminjam', 'manual')->set('nama', 'Pak Dedi')->set('unit', 'Ormawa HIMA')
        ->set('keperluan', 'Seminar')->set('kembaliTanggal', now()->addDays(2)->toDateString())->set('kembaliJam', '15:00')
        ->call('catat')
        ->assertHasNoErrors()
        ->assertSee('PJM-'.now()->year.'-00001')->assertSet('daftar', []);

    expect(Peminjaman::first())->nama_peminjam->toBe('Pak Dedi')->unit_peminjam->toBe('Ormawa HIMA')->keperluan->toBe('Seminar');
});

it('/keranjang menampilkan galat bila aset tak tersedia dan tidak mencatat apa pun', function () {
    $r = ruangPjm('R-1');
    $pic = orangPjm('pic-ruangan', [$r]);
    $aset = asetPinjam($r);
    Peminjaman::factory()->status(StatusPeminjaman::Dipinjam)->untuk($aset)->create();
    $this->actingAs($pic);

    Livewire::test(Keranjang::class)
        ->set('daftar', [$aset->id])
        ->set('modePeminjam', 'manual')->set('nama', 'X')->set('keperluan', 'Y')
        ->set('kembaliTanggal', now()->addDay()->toDateString())->set('kembaliJam', '10:00')
        ->call('catat')
        ->assertHasErrors('aset');

    expect(Peminjaman::count())->toBe(1);
});

it('/keranjang mewajibkan keperluan dan peminjam dari daftar saat mode akun', function () {
    $r = ruangPjm('R-1');
    $this->actingAs(orangPjm('pic-ruangan', [$r]));

    Livewire::test(Keranjang::class)
        ->set('daftar', [asetPinjam($r)->id])->set('keperluan', '')
        ->call('catat')->assertHasErrors(['keperluan', 'peminjamUserId']);
});

it('pencarian peminjam akun hanya menampilkan civitas aktif', function () {
    $r = ruangPjm('R-1');
    $this->actingAs(orangPjm('pic-ruangan', [$r]));
    $ada = orangPjm('civitas');
    $ada->update(['name' => 'Rina Pencari']);
    orangPjm('civitas')->update(['name' => 'Rina Nonaktif', 'aktif' => false]);
    orangPjm('pimpinan')->update(['name' => 'Rina Pimpinan']);

    Livewire::test(Keranjang::class)->set('cariPeminjam', 'Rina')
        ->assertSee('Rina Pencari')->assertDontSee('Rina Nonaktif')->assertDontSee('Rina Pimpinan')
        ->call('pilihPeminjam', $ada->id)->assertSet('peminjamUserId', $ada->id);
});
