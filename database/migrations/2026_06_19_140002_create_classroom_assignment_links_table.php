<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Taut latihan/tugas ke banyak kelas (pivot). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_assignment_links', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('assignment_id');
            $table->uuid('classroom_id');
            $table->timestamps();

            $table->foreign('assignment_id')->references('uuid')->on('classroom_assignments')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->unique(['assignment_id', 'classroom_id'], 'classroom_assign_links_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_assignment_links');
    }
};
