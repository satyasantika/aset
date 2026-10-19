<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): pengganti `Cache::lock` untuk penomoran dan seksi kritis.
 * Satu baris per kunci (mis. `nomor:PJM:2026`, `mutex:inventarisasi`); pemanggil mengunci barisnya dengan
 * `lockForUpdate()` di dalam transaksi. Tabel infrastruktur penghitung, bukan data domain: kunci primer berupa
 * teks (pengecualian §4a butir 6), tidak pernah tampil di URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penomoran', function (Blueprint $table) {
            $table->string('kunci', 100)->primary();
            $table->unsignedBigInteger('terakhir')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penomoran');
    }
};
