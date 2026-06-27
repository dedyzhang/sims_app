<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_denah_ruangan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->foreignUuid('denah_id')->constrained('sarpras_denah')->cascadeOnDelete();
            $table->string('kode');                 // mis. "7A"
            $table->string('nama')->nullable();     // mis. "Kelas 7A"
            // KOORDINAT PERSEN (0-100) — bukan pixel absolut, supaya responsif.
            $table->decimal('pos_x', 5, 2)->default(50);
            $table->decimal('pos_y', 5, 2)->default(50);
            // Gambar denah ruangan + foto ruangan (path relatif disk public).
            $table->string('gambar_denah_path')->nullable();
            $table->string('foto_path')->nullable();
            $table->unsignedInteger('kapasitas')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->unique(['denah_id', 'kode']);
            $table->index(['school_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_denah_ruangan');
    }
};
