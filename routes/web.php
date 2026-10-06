<?php

use App\Http\Controllers\LabelPdfController;
use App\Http\Controllers\Publik\LookupAsetController;
use App\Livewire\Keranjang;
use App\Livewire\Pindai;
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

Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::get('/pindai', Pindai::class)->name('pindai');
    Route::get('/keranjang', Keranjang::class)->name('keranjang');

    Route::match(['get', 'post'], '/cetak/label', LabelPdfController::class)->name('cetak.label');
});
