<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_laporan_kerusakan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->string('kode')->nullable();
            $table->foreignUuid('aset_id')->nullable()
                ->constrained('sarpras_aset')->nullOnDelete();
            $table->foreignUuid('ruangan_id')->nullable()
                ->constrained('sarpras_denah_ruangan')->nullOnDelete();
            $table->foreignUuid('pelapor_id')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->text('deskripsi');
            $table->enum('urgensi', ['rendah', 'sedang', 'tinggi', 'darurat'])->default('sedang');
            $table->enum('status', ['dilaporkan', 'diterima', 'ditolak', 'selesai'])
                ->default('dilaporkan');
            $table->text('alasan_tolak')->nullable();
            $table->foreignUuid('ditangani_oleh')->nullable()
                ->constrained('users', 'uuid')->nullOnDelete();
            $table->timestamp('ditangani_pada')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'urgensi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_laporan_kerusakan');
    }
};
