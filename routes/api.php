<?php

use App\Http\Controllers\Api\RuanganController;
use App\Http\Controllers\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API v1 untuk Surat/OrmawaHub: Sanctum + ability per klien, 60 permintaan/menit/token.
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api-klien'])->group(function () {
    Route::middleware('ability:ruangan:baca,ruangan:pakai')->group(function () {
        Route::get('/ruangan', [RuanganController::class, 'index']);
        Route::get('/ruangan/{kode}/jadwal', [RuanganController::class, 'jadwal']);
    });

    Route::post('/ruangan/{kode}/pemakaian', [RuanganController::class, 'pakai'])->middleware('ability:ruangan:pakai');
});
