<?php

use App\Exceptions\FiturNonaktif;
use App\Filament\Pages\PengaturanSistem;
use App\Models\PengaturanItem;
use App\Models\User;
use App\Support\Pengaturan;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Cache::flush();
});

function aktor(string $peran): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');
    test()->actingAs($user);

    return $user;
}

it('menyemai pengaturan secara idempoten tanpa menimpa perubahan administrator', function () {
    $this->seed(PengaturanSeeder::class);
    Pengaturan::simpan('nama_kampus', 'Kampus Uji');
    $this->seed(PengaturanSeeder::class);

    expect(PengaturanItem::count())->toBe(count(Pengaturan::BAWAAN))
        ->and(Pengaturan::ambil('nama_kampus'))->toBe('Kampus Uji')
        ->and(Pengaturan::ambil('retensi_peminjaman_bulan'))->toBe(36)
        ->and(Pengaturan::ambil('maks_hari_pinjam'))->toBe(14)
        ->and(Pengaturan::ambil('ambang_pengingat_inventarisasi_tahun'))->toBe(4);
});

it('memakai nilai bawaan bila belum ada di basis data dan men-cache hasilnya', function () {
    expect(Pengaturan::ambil('kota_surat'))->toBe('Tasikmalaya')
        ->and(Pengaturan::ambil('tidak-ada', 'x'))->toBe('x');

    Pengaturan::simpan('kota_surat', 'Bandung');
    expect(Pengaturan::ambil('kota_surat'))->toBe('Bandung')
        ->and(Cache::has(Pengaturan::KUNCI_CACHE))->toBeTrue();
});

it('membaca toggle fitur dari pengaturan dan menegakkannya lewat pastikanFitur', function () {
    foreach (Pengaturan::FITUR as $fitur) {
        expect(Pengaturan::fitur($fitur))->toBeTrue();
    }

    Pengaturan::simpan('fitur_mutasi', false);

    expect(Pengaturan::fitur('mutasi'))->toBeFalse()
        ->and(Pengaturan::fitur('peminjaman'))->toBeTrue()
        ->and(fn () => Pengaturan::pastikanFitur('mutasi'))->toThrow(FiturNonaktif::class)
        ->and(fn () => Pengaturan::pastikanFitur('peminjaman'))->not->toThrow(FiturNonaktif::class);
});

it('hanya super-admin yang membuka halaman pengaturan sistem', function (string $peran, bool $boleh) {
    aktor($peran);

    $respons = $this->get('/admin/pengaturan-sistem');
    $boleh ? $respons->assertOk() : $respons->assertForbidden();
})->with([['super-admin', true], ['admin-bmn', false], ['pic-ruangan', false], ['pimpinan', false]]);

it('super-admin menyimpan pengaturan dari halaman', function () {
    $this->seed(PengaturanSeeder::class);
    aktor('super-admin');

    Livewire::test(PengaturanSistem::class)
        ->assertFormSet(['nama_kampus' => 'Universitas Siliwangi', 'fitur_mutasi' => true])
        ->fillForm(['nama_kampus' => 'Unsil Baru', 'fitur_mutasi' => false, 'maks_hari_pinjam' => 7])
        ->call('simpan')
        ->assertHasNoFormErrors();

    expect(Pengaturan::ambil('nama_kampus'))->toBe('Unsil Baru')
        ->and(Pengaturan::fitur('mutasi'))->toBeFalse()
        ->and(Pengaturan::ambil('maks_hari_pinjam'))->toBe(7);
});

it('izin halaman pengaturan hanya untuk pengaturan.kelola', function () {
    aktor('admin-bmn');
    expect(PengaturanSistem::canAccess())->toBeFalse();

    aktor('super-admin');
    expect(PengaturanSistem::canAccess())->toBeTrue();
});

it('memvalidasi nilai ambang pada form', function () {
    aktor('super-admin');

    Livewire::test(PengaturanSistem::class)
        ->fillForm(['ambang_pengingat_inventarisasi_tahun' => 9])
        ->call('simpan')
        ->assertHasFormErrors(['ambang_pengingat_inventarisasi_tahun']);
});
