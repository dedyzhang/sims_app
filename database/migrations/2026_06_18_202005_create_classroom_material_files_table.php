<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lampiran file materi (gambar/PDF). Selalu hasil kompresi (lihat FileCompressionService). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_material_files', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('material_id');

            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_original')->default(0);
            $table->unsignedBigInteger('size_compressed')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->foreign('material_id')->references('uuid')->on('classroom_materials')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_material_files');
    }
};
