<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taut materi ke banyak kelas (pivot). Satu materi = satu record, dapat tampil &
 * disunting sekaligus di beberapa kelas. Tabel baru milik modul ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_material_links', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('material_id');
            $table->uuid('classroom_id');
            $table->timestamps();

            $table->foreign('material_id')->references('uuid')->on('classroom_materials')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->unique(['material_id', 'classroom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_material_links');
    }
};
