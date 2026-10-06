<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dbr_versi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ruangan_id')->nullable()->constrained('ruangan');
            $table->string('jenis', 5)->default('dbr');
            $table->unsignedInteger('versi');
            $table->string('status', 20)->default('draf');
            $table->json('snapshot');
            $table->foreignUuid('disetujui_pic_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pic_pada')->nullable();
            $table->foreignUuid('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disahkan_pada')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            // MySQL/SQLite mengabaikan NULL pada UNIQUE; penomoran DBL dijaga Action dengan lock.
            $table->unique(['ruangan_id', 'jenis', 'versi']);
            $table->index(['ruangan_id', 'jenis', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dbr_versi');
    }
};
