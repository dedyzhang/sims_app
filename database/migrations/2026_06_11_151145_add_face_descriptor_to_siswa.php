<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            // Array of face descriptors (128 float each) — biometrik, bukan foto
            $table->json('face_descriptor')->nullable()->after('foto');
            $table->timestamp('face_registered_at')->nullable()->after('face_descriptor');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn(['face_descriptor', 'face_registered_at']);
        });
    }
};
