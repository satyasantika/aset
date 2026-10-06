<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data migrasi SIMAN-2 tidak punya pelaku terverifikasi (07-MIGRASI §3): pemohon mutasi dan pencatat peminjaman lama
 * berupa teks. Kolom pelaku dibuat nullable; untuk transaksi baru tetap selalu terisi oleh Action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mutasi', function (Blueprint $table) {
            $table->foreignUuid('diajukan_oleh')->nullable()->change();
        });

        Schema::table('peminjaman', function (Blueprint $table) {
            $table->foreignUuid('dicatat_oleh')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Tidak dapat dipulihkan bila sudah ada baris tanpa pelaku.
    }
};
