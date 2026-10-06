<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->foreignUuid('ruangan_asal_id')->constrained('ruangan');
            $table->foreignUuid('ruangan_tujuan_id')->constrained('ruangan');
            $table->text('alasan');
            $table->string('status', 20)->default('diajukan');
            $table->foreignUuid('diajukan_oleh')->constrained('users');
            $table->foreignUuid('diputuskan_oleh')->nullable()->constrained('users');
            $table->timestamp('diputuskan_pada')->nullable();
            $table->text('catatan_keputusan')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('mutasi_item', function (Blueprint $table) {
            $table->foreignUuid('mutasi_id')->constrained('mutasi')->cascadeOnDelete();
            $table->foreignUuid('aset_id')->constrained('aset');
            $table->primary(['mutasi_id', 'aset_id']);
            $table->index('aset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_item');
        Schema::dropIfExists('mutasi');
    }
};
