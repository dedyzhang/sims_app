<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Topik diskusi. classroom_id = id_kelas (kelas.uuid), subject_id = id_pelajaran (pelajarans.uuid).
 * Keduanya NULLABLE (forum umum/pengumuman bisa tanpa kelas/mapel).
 * Tidak ada school_id (app ini single-school).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_topics', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            $table->uuid('id_kelas')->nullable();
            $table->uuid('id_pelajaran')->nullable();
            $table->uuid('created_by');

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body');

            $table->enum('audience', ['siswa_guru', 'termasuk_ortu'])->default('siswa_guru');
            $table->enum('category', ['akademik', 'kesiswaan', 'sarpras', 'umum', 'pengumuman'])->default('umum');

            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);

            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_kelas')->references('uuid')->on('kelas')->nullOnDelete();
            $table->foreign('id_pelajaran')->references('uuid')->on('pelajarans')->nullOnDelete();
            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnDelete();

            $table->index(['category', 'is_pinned', 'last_activity_at']);
            $table->index('id_kelas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_topics');
    }
};
