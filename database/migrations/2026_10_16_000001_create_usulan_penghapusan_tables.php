<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usulan_penghapusan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->text('alasan');
            $table->string('status', 20)->default('draf');
            $table->string('nomor_sk', 100)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->foreignUuid('pengusul_id')->constrained('users');
            $table->foreignUuid('pemutus_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->text('catatan_keputusan')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('usulan_penghapusan_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usulan_id')->constrained('usulan_penghapusan')->cascadeOnDelete();
            $table->foreignUuid('aset_id')->constrained('aset');
            $table->string('alasan_item', 15);
            $table->timestamps();

            $table->unique(['usulan_id', 'aset_id']);
            $table->index('aset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_penghapusan_item');
        Schema::dropIfExists('usulan_penghapusan');
    }
};
