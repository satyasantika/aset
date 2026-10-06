<?php

use App\Http\Controllers\BeritaAcaraPdfController;
use App\Http\Controllers\DbrPdfController;
use App\Http\Controllers\LabelPdfController;
use App\Http\Controllers\Publik\LaporKerusakanController;
use App\Http\Controllers\Publik\LookupAsetController;
use App\Livewire\AjukanPinjam;
use App\Livewire\InventarisasiRuanganHalaman;
use App\Livewire\Keranjang;
use App\Livewire\Pindai;
use App\Livewire\PinjamanSaya;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Halaman login tunggal ada di panel; alias ini dipakai middleware `auth` bila tamu membuka rute terlindungi.
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// Rute publik (BR-17, BR-18): tanpa login, dibatasi 30 permintaan/menit/IP.
Route::middleware('throttle:lookup')->group(function () {
    Route::get('/a/{aset}', [LookupAsetController::class, 'tampil'])->whereUuid('aset')->name('publik.aset');
    Route::get('/l/{kode}', [LookupAsetController::class, 'labelLama'])->where('kode', '[A-Za-z0-9._\-]{1,100}')->name('publik.label-lama');
});

// Lapor kerusakan (BR-12): tampilan formulir dibatasi seperti lookup; pengiriman 5/jam/IP.
Route::get('/lapor-kerusakan/{aset}', [LaporKerusakanController::class, 'form'])->whereUuid('aset')->middleware('throttle:lookup')->name('publik.lapor');
Route::post('/lapor-kerusakan/{aset}', [LaporKerusakanController::class, 'kirim'])->whereUuid('aset')->middleware('throttle:lapor')->name('publik.lapor.kirim');

Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::get('/pindai', Pindai::class)->name('pindai');
    Route::get('/inventarisasi/{periode}/{ruangan}', InventarisasiRuanganHalaman::class)->whereUuid(['periode', 'ruangan'])->name('inventarisasi.ruangan');
    Route::get('/keranjang', Keranjang::class)->name('keranjang');
    Route::get('/pinjam', AjukanPinjam::class)->name('pinjam');
    Route::get('/pinjaman-saya', PinjamanSaya::class)->name('pinjaman-saya');

    Route::match(['get', 'post'], '/cetak/label', LabelPdfController::class)->name('cetak.label');
    Route::get('/cetak/dbr/{dbr}', DbrPdfController::class)->whereUuid('dbr')->name('cetak.dbr');
    Route::get('/cetak/berita-acara/{periode}', [BeritaAcaraPdfController::class, 'pdf'])->whereUuid('periode')->name('cetak.berita-acara');
    Route::get('/ekspor/inventarisasi/{periode}/selisih', [BeritaAcaraPdfController::class, 'selisih'])->whereUuid('periode')->name('ekspor.inventarisasi.selisih');
});
