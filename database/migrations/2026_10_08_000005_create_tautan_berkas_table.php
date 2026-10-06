<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tautan_berkas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('pemilik');
            $table->string('jenis', 30);
            $table->string('label', 150);
            $table->string('url', 2048);
            $table->string('penyedia', 20)->default('lainnya');
            $table->string('drive_file_id', 128)->nullable();
            $table->string('status_cek', 25)->default('belum');
            $table->timestamp('dicek_pada')->nullable();
            $table->foreignUuid('ditambahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pemilik_type', 'pemilik_id', 'jenis']);
            $table->index('status_cek');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tautan_berkas');
    }
};
