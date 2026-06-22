<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pengumpulan tugas oleh siswa. 1 submission per (tugas, siswa). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_submissions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('assignment_id');
            $table->uuid('classroom_id');
            $table->uuid('student_id');

            $table->longText('body')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->integer('score')->nullable();
            $table->longText('feedback')->nullable();
            $table->uuid('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->enum('status', ['draft', 'submitted', 'returned', 'graded'])->default('draft');

            $table->timestamps();

            $table->foreign('assignment_id')->references('uuid')->on('classroom_assignments')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->foreign('student_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreign('graded_by')->references('uuid')->on('users')->nullOnDelete();
            $table->unique(['assignment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_submissions');
    }
};
