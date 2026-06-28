<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_pengadaan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->string('kode');
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->foreignUuid('diajukan_oleh')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak', 'selesai'])
                ->default('diajukan');
            // UANG: integer rupiah (BCMath).
            $table->unsignedBigInteger('total_estimasi')->default(0);
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
        Schema::dropIfExists('sarpras_pengadaan');
    }
};
