<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_inventarisasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', 150);
            $table->string('jenis', 20)->default('sensus');
            $table->date('mulai');
            $table->date('selesai_rencana')->nullable();
            $table->string('status', 15)->default('rencana');
            $table->timestamp('dibuka_pada')->nullable();
            $table->timestamp('ditutup_pada')->nullable();
            $table->json('berita_acara')->nullable();
            $table->foreignUuid('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disahkan_pada')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('inventarisasi_ruangan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('periode_id')->constrained('periode_inventarisasi')->cascadeOnDelete();
            $table->foreignUuid('ruangan_id')->constrained('ruangan');
            $table->foreignUuid('petugas_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 15)->default('belum');
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();

            $table->unique(['periode_id', 'ruangan_id']);
            $table->index(['ruangan_id', 'status']);
        });

        Schema::create('inventarisasi_petugas', function (Blueprint $table) {
            $table->foreignUuid('inventarisasi_ruangan_id')->constrained('inventarisasi_ruangan')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['inventarisasi_ruangan_id', 'user_id']);
        });

        Schema::create('hasil_inventarisasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventarisasi_ruangan_id')->constrained('inventarisasi_ruangan')->cascadeOnDelete();
            $table->foreignUuid('aset_id')->nullable()->constrained('aset');
            $table->string('hasil', 20);
            $table->char('kondisi_ditemukan', 2)->nullable();
            $table->text('deskripsi_temuan')->nullable();
            $table->foreignUuid('dipindai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dipindai_pada')->nullable();
            $table->timestamps();

            // NULL (temuan berlebih tanpa aset) tidak dibatasi unik oleh basis data
            $table->unique(['inventarisasi_ruangan_id', 'aset_id']);
            $table->index('hasil');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_inventarisasi');
        Schema::dropIfExists('inventarisasi_petugas');
        Schema::dropIfExists('inventarisasi_ruangan');
        Schema::dropIfExists('periode_inventarisasi');
    }
};
