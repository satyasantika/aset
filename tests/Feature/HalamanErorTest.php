<?php

use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menampilkan halaman 404 representatif dengan tombol kembali dan beranda', function () {
    $this->get('/rute-yang-tidak-ada')
        ->assertNotFound()
        ->assertSee('404')
        ->assertSee('Halaman tidak ditemukan')
        ->assertSee('Kembali', false)
        ->assertSee(htmlspecialchars(url('/')), false);
});

it('menampilkan halaman 403 representatif saat akses ditolak', function () {
    $this->seed(PeranDanIzinSeeder::class);
    $user = User::factory()->create(['aktif' => true]);
    $user->assignRole('pic-ruangan');

    $this->actingAs($user)->get('/admin/pengguna')
        ->assertForbidden()
        ->assertSee('403')
        ->assertSee('Akses ditolak');
});

it('setiap halaman error (403, 404, 419, 429, 500, 503) merender dengan kode, judul, dan dua tombol navigasi', function (string $kode) {
    $html = view('errors.'.$kode)->render();

    expect($html)->toContain($kode)
        ->toContain('Kembali')
        ->toContain('Beranda')
        ->toContain(url('/'));
})->with(['403', '404', '419', '429', '500', '503']);
