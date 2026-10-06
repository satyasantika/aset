<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruangan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->foreignUuid('gedung_id')->nullable()->constrained('gedung')->nullOnDelete();
            $table->foreignUuid('kategori_ruangan_id')->nullable()->constrained('kategori_ruangan')->nullOnDelete();
            $table->string('lantai', 10)->nullable();
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->boolean('dapat_dipinjam')->default(false);
            $table->decimal('luas_m2', 8, 2)->nullable();
            $table->json('k3l')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('dapat_dipinjam');
        });

        Schema::create('prodi_ruangan', function (Blueprint $table) {
            $table->foreignUuid('prodi_id')->constrained('prodi')->cascadeOnDelete();
            $table->foreignUuid('ruangan_id')->constrained('ruangan')->cascadeOnDelete();
            $table->primary(['prodi_id', 'ruangan_id']);
        });

        Schema::create('ruangan_pic', function (Blueprint $table) {
            $table->foreignUuid('ruangan_id')->constrained('ruangan')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('utama')->default(false);
            $table->timestamps();
            $table->primary(['ruangan_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruangan_pic');
        Schema::dropIfExists('prodi_ruangan');
        Schema::dropIfExists('ruangan');
    }
};
