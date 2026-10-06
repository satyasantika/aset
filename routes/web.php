<?php

use App\Http\Controllers\LabelPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Halaman login tunggal ada di panel; alias ini dipakai middleware `auth` bila tamu membuka rute terlindungi.
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::match(['get', 'post'], '/cetak/label', LabelPdfController::class)->name('cetak.label');
});
