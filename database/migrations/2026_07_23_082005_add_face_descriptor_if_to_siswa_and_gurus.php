<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom TERPISAH dari `face_descriptor` (Human.js) — sengaja dipisah, bukan menimpa kolom
     * lama, supaya setting `face_engine` bisa dibalik ke 'human' kapan saja tanpa kehilangan
     * data lama sama sekali. Embedding InsightFace (ArcFace) tidak sebanding secara matematis
     * dgn embedding Human.js (ruang vektor beda), makanya wajib kolom sendiri.
     */
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->json('face_descriptor_if')->nullable()->after('face_registered_at');
        });
        Schema::table('gurus', function (Blueprint $table) {
            $table->json('face_descriptor_if')->nullable()->after('face_registered_at');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn('face_descriptor_if');
        });
        Schema::table('gurus', function (Blueprint $table) {
            $table->dropColumn('face_descriptor_if');
        });
    }
};
