<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_denah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->string('nama');                 // mis. "Gedung A - Lantai 1"
            $table->string('gedung')->nullable();
            $table->string('lantai')->nullable();
            // Path relatif gambar denah (disk public). Nullable -> pakai placeholder.
            $table->string('gambar_path')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'gedung', 'lantai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_denah');
    }
};
