<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiket_pemeliharaan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->foreignUuid('aset_id')->constrained('aset');
            $table->string('sumber', 20);
            $table->uuid('sumber_id')->nullable();
            $table->text('deskripsi');
            $table->string('nama_pelapor', 150)->nullable();
            $table->string('kontak_pelapor', 50)->nullable();
            $table->foreignUuid('pelapor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('ip_hash', 64)->nullable();
            $table->string('status', 25)->default('baru');
            $table->text('tindakan')->nullable();
            $table->decimal('biaya', 15, 2)->nullable();
            $table->foreignUuid('ditangani_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();

            $table->index(['aset_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['aset_id', 'sumber', 'sumber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiket_pemeliharaan');
    }
};
