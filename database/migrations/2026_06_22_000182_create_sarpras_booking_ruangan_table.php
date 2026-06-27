<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_booking_ruangan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->foreignUuid('ruangan_id')
                ->constrained('sarpras_denah_ruangan')->cascadeOnDelete();
            $table->foreignUuid('pemohon_id')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->text('keperluan');
            $table->dateTime('mulai');
            $table->dateTime('selesai');
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak', 'selesai'])
                ->default('diajukan');
            $table->timestamps();

            // Index untuk pengecekan bentrok (overlap) per ruangan.
            $table->index(['ruangan_id', 'mulai', 'selesai']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_booking_ruangan');
    }
};
