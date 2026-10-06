<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aset', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status_bmn', 20)->default('tercatat');
            $table->string('kode_barang', 20)->nullable();
            $table->unsignedInteger('nup')->nullable();
            $table->string('kode_internal', 30)->nullable()->unique();
            $table->string('nama');
            $table->string('merk_tipe')->nullable();
            $table->text('spesifikasi')->nullable();
            $table->smallInteger('tahun_perolehan')->nullable();
            $table->date('tanggal_perolehan')->nullable();
            $table->decimal('nilai_perolehan', 15, 2)->nullable();
            $table->string('sumber_perolehan', 20)->nullable();
            $table->string('sumber_dana', 50)->nullable();
            $table->string('nomor_dokumen_perolehan', 100)->nullable();
            $table->string('penguasaan', 30)->default('milik_sendiri');
            $table->foreignUuid('ruangan_id')->nullable()->constrained('ruangan');
            $table->string('lokasi_lainnya', 150)->nullable();
            $table->char('kondisi', 2)->default('B');
            $table->string('status', 20)->default('aktif');
            $table->boolean('dapat_dipinjam')->default(false);
            $table->string('nomor_sk_penghapusan', 100)->nullable();
            $table->date('tanggal_sk_penghapusan')->nullable();
            $table->dateTime('dicetak_pada')->nullable();
            $table->boolean('label_perlu_cetak_ulang')->default(false);
            $table->string('kelompok_pengadaan', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kode_barang', 'nup']);
            $table->index(['ruangan_id', 'status', 'kondisi']);
            $table->index('status');
            $table->index('kode_barang');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE aset ADD CONSTRAINT aset_kondisi_check CHECK (kondisi IN ('B','RR','RB'))");
            DB::statement("ALTER TABLE aset ADD CONSTRAINT aset_status_bmn_check CHECK (status_bmn <> 'tercatat' OR (kode_barang IS NOT NULL AND nup IS NOT NULL))");
            DB::statement("ALTER TABLE aset ADD CONSTRAINT aset_sk_hapus_check CHECK (status <> 'dihapus' OR nomor_sk_penghapusan IS NOT NULL)");
            DB::statement('ALTER TABLE aset ADD FULLTEXT aset_nama_merk_fulltext (nama, merk_tipe)');
        }

        Schema::create('riwayat_kondisi_aset', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aset_id')->constrained('aset');
            $table->char('dari', 2)->nullable();
            $table->char('ke', 2);
            $table->string('sumber', 30);
            $table->uuid('sumber_id')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignUuid('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['aset_id', 'created_at']);
        });

        Schema::create('riwayat_lokasi_aset', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aset_id')->constrained('aset');
            $table->foreignUuid('dari_ruangan_id')->nullable()->constrained('ruangan');
            $table->foreignUuid('ke_ruangan_id')->nullable()->constrained('ruangan');
            $table->string('sumber', 30);
            $table->uuid('mutasi_id')->nullable();
            $table->foreignUuid('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['aset_id', 'created_at']);
        });

        Schema::create('riwayat_status_aset', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aset_id')->constrained('aset');
            $table->string('dari', 20)->nullable();
            $table->string('ke', 20);
            $table->text('catatan')->nullable();
            $table->foreignUuid('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['aset_id', 'created_at']);
        });

        Schema::create('label_lama', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('teks', 100)->unique();
            $table->foreignUuid('aset_id')->constrained('aset');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_lama');
        Schema::dropIfExists('riwayat_status_aset');
        Schema::dropIfExists('riwayat_lokasi_aset');
        Schema::dropIfExists('riwayat_kondisi_aset');
        Schema::dropIfExists('aset');
    }
};
