<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul "Ruang Kelas" (Classroom) B'tive. Adaptasi ke smp_v6:
 * - Tanpa school_id (single-school).
 * - subject_id → id_pelajaran (pelajarans.uuid). class refs → kelas.uuid. users.uuid.
 * - academic_year_id DIHILANGKAN (app ini tak punya tabel academic_years; tahun ada di semesters.tahun).
 * - semester_id → id_semester (semesters.id = INTEGER, bukan uuid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            $table->unsignedBigInteger('id_semester')->nullable();  // semesters.id (integer)
            $table->uuid('id_pelajaran')->nullable();               // pelajarans.uuid (subject)
            $table->uuid('created_by');                             // users.uuid (guru pembuat)

            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('cover_color', 20)->default('#2563eb');
            $table->enum('status', ['draft', 'scheduled', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_publish_at')->nullable();
            $table->string('class_code', 12)->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_semester')->references('id')->on('semesters')->nullOnDelete();
            $table->foreign('id_pelajaran')->references('uuid')->on('pelajarans')->nullOnDelete();
            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnDelete();

            $table->index(['status', 'scheduled_publish_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
