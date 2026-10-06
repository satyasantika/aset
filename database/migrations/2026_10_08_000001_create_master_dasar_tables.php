<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gedung', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->string('alamat')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('kategori_ruangan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama', 100)->unique();
            $table->boolean('adalah_laboratorium')->default(false);
            $table->boolean('adalah_ruang_kelas')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prodi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama', 150);
            $table->string('kode_eksternal', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prodi');
        Schema::dropIfExists('kategori_ruangan');
        Schema::dropIfExists('gedung');
    }
};
