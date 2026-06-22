<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SATU-SATUNYA sentuhan ke tabel lama: tambah kolom nullable classroom_id (UUID)
 * ke forum_topics (di app ini forum = forum_topics/forum_comments, BUKAN forum_threads).
 * Forum lama (classroom_id null) tetap berfungsi 100%. Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->uuid('classroom_id')->nullable()->after('id_pelajaran');
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->dropColumn('classroom_id');
        });
    }
};
