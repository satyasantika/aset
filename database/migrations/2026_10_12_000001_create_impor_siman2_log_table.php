<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impor_siman2_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sheet', 40);
            $table->string('id_lama', 100);
            $table->uuid('id_baru')->nullable();
            $table->string('status', 15);
            $table->text('pesan')->nullable();
            $table->timestamps();

            $table->unique(['sheet', 'id_lama']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impor_siman2_log');
    }
};
