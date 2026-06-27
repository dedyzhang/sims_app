<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_aset', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->string('kode');                  // kode unik aset
            $table->string('nama');
            $table->foreignUuid('kategori_id')->nullable()
                ->constrained('sarpras_kategori_aset')->nullOnDelete();
            $table->foreignUuid('ruangan_id')->nullable()
                ->constrained('sarpras_denah_ruangan')->nullOnDelete();
            $table->string('merk')->nullable();
            // Spesifikasi key-value disimpan sebagai JSON.
            $table->json('spesifikasi')->nullable();
            $table->enum('kondisi', ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'])
                ->default('baik');
            $table->enum('status', ['aktif', 'dipinjam', 'perbaikan', 'dihapus', 'dimutasi'])
                ->default('aktif');
            $table->date('tgl_perolehan')->nullable();
            // UANG: integer rupiah (BCMath). JANGAN float.
            $table->unsignedBigInteger('nilai_perolehan')->default(0);
            $table->string('sumber_dana')->nullable();
            $table->string('foto_path')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'kode']);
            $table->index(['school_id', 'kategori_id']);
            $table->index(['school_id', 'kondisi']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_aset');
    }
};
