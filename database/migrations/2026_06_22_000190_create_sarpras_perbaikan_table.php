<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_perbaikan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->string('kode');
            $table->foreignUuid('aset_id')->nullable()
                ->constrained('sarpras_aset')->nullOnDelete();
            // Terhubung ke laporan kerusakan (opsional).
            $table->foreignUuid('laporan_id')->nullable()
                ->constrained('sarpras_laporan_kerusakan')->nullOnDelete();
            $table->foreignUuid('teknisi_id')->nullable()
                ->constrained('sarpras_teknisi')->nullOnDelete();
            $table->text('deskripsi');
            $table->enum('status', ['antri', 'dikerjakan', 'selesai', 'batal'])
                ->default('antri');
            // UANG: integer rupiah (BCMath).
            $table->unsignedBigInteger('biaya')->default(0);
            $table->date('tgl_mulai')->nullable();
            $table->date('tgl_selesai')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'kode']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_perbaikan');
    }
};
