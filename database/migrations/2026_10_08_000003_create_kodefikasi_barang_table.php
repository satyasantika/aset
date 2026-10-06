<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kodefikasi_barang', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('uraian');
            $table->unsignedTinyInteger('tingkat');
            $table->string('induk_kode', 20)->nullable()->index();
            $table->string('kategori_lokal', 100)->nullable();
            $table->timestamps();

            $table->index('tingkat');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE kodefikasi_barang ADD FULLTEXT kodefikasi_barang_uraian_fulltext (uraian)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kodefikasi_barang');
    }
};
