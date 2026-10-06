<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peminjaman', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->string('jenis_peminjam', 20)->default('civitas');
            $table->foreignUuid('peminjam_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_peminjam', 150);
            $table->string('kontak_peminjam', 50)->nullable();
            $table->string('unit_peminjam', 150)->nullable();
            $table->text('keperluan');
            $table->dateTime('mulai');
            $table->dateTime('rencana_kembali');
            $table->string('status', 20);
            $table->foreignUuid('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diputuskan_pada')->nullable();
            $table->text('catatan_keputusan')->nullable();
            $table->foreignUuid('diserahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diserahkan_pada')->nullable();
            $table->foreignUuid('diterima_kembali_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dikembalikan_pada')->nullable();
            $table->foreignUuid('dicatat_oleh')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'mulai', 'rencana_kembali']);
            $table->index('peminjam_user_id');
        });

        Schema::create('peminjaman_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('peminjaman_id')->constrained('peminjaman')->cascadeOnDelete();
            $table->foreignUuid('aset_id')->constrained('aset');
            $table->char('kondisi_saat_pinjam', 2);
            $table->char('kondisi_saat_kembali', 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['peminjaman_id', 'aset_id']);
            $table->index('aset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjaman_item');
        Schema::dropIfExists('peminjaman');
    }
};
