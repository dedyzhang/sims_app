<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot many-to-many tidak butuh PK uuid sendiri (sync() tidak mengisinya → NOT NULL gagal).
 * Buat ulang dengan composite primary key (konten_id, classroom_id). Tabel masih kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('classroom_material_links');
        Schema::dropIfExists('classroom_assignment_links');

        Schema::create('classroom_material_links', function (Blueprint $table) {
            $table->uuid('material_id');
            $table->uuid('classroom_id');
            $table->timestamps();
            $table->foreign('material_id')->references('uuid')->on('classroom_materials')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->primary(['material_id', 'classroom_id']);
        });

        Schema::create('classroom_assignment_links', function (Blueprint $table) {
            $table->uuid('assignment_id');
            $table->uuid('classroom_id');
            $table->timestamps();
            $table->foreign('assignment_id')->references('uuid')->on('classroom_assignments')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->primary(['assignment_id', 'classroom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_material_links');
        Schema::dropIfExists('classroom_assignment_links');
    }
};
