<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_peminjaman', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->string('kode');
            $table->foreignUuid('peminjam_id')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->text('keperluan');
            $table->date('tgl_pinjam');
            $table->date('tgl_kembali_rencana');
            $table->date('tgl_kembali_aktual')->nullable();
            $table->enum('status', [
                'diajukan', 'disetujui', 'ditolak', 'dipinjam', 'dikembalikan', 'terlambat',
            ])->default('diajukan');
            $table->foreignUuid('disetujui_oleh')->nullable()
                ->constrained('users', 'uuid')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->text('alasan_tolak')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'kode']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_peminjaman');
    }
};
