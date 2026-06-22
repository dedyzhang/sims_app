<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lampiran jawaban siswa (banyak file gambar/PDF, hasil kompresi). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_submission_files', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('submission_id');

            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_original')->default(0);
            $table->unsignedBigInteger('size_compressed')->nullable();

            $table->timestamps();

            $table->foreign('submission_id')->references('uuid')->on('classroom_submissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_submission_files');
    }
};
