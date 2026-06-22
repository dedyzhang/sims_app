<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pivot multi-rombel: satu Ruang Kelas untuk beberapa kelas (kelas lama). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_kelas', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('classroom_id');
            $table->uuid('id_kelas');   // ref kelas lama (tidak diubah)
            $table->timestamps();

            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->foreign('id_kelas')->references('uuid')->on('kelas')->cascadeOnDelete();
            $table->unique(['classroom_id', 'id_kelas']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_kelas');
    }
};
