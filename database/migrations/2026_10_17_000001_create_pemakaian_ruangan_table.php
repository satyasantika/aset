<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemakaian_ruangan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ruangan_id')->constrained('ruangan')->cascadeOnDelete();
            $table->dateTime('mulai');
            $table->dateTime('selesai');
            $table->string('kegiatan', 255);
            $table->string('sumber', 10)->default('surat');
            $table->string('referensi_eksternal', 100)->nullable();
            $table->string('status', 12)->default('terjadwal');
            $table->foreignUuid('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ruangan_id', 'mulai', 'selesai']);
            $table->unique(['sumber', 'referensi_eksternal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemakaian_ruangan');
    }
};
