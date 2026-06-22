<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Latihan/Tugas/Kuis. Lampiran soal di tabel terpisah. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_assignments', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('classroom_id');
            $table->uuid('created_by');

            $table->string('title');
            $table->longText('instructions')->nullable();
            $table->enum('type', ['tugas', 'latihan', 'kuis'])->default('tugas');
            $table->integer('max_score')->default(100);
            $table->boolean('allow_late')->default(false);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('scheduled_publish_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_assignments');
    }
};
