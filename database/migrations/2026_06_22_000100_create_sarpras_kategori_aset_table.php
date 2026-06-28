<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_kategori_aset', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->string('kode')->nullable();
            $table->string('nama');
            // Hierarki opsional (kategori induk).
            $table->uuid('parent_id')->nullable()->index();
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'kode']);
            $table->index(['school_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_kategori_aset');
    }
};
