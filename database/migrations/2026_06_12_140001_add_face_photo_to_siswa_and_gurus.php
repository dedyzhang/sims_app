<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot wajah (data URL JPEG kecil) untuk validasi visual
        Schema::table('siswa', function (Blueprint $table) {
            $table->longText('face_photo')->nullable()->after('face_descriptor');
        });
        Schema::table('gurus', function (Blueprint $table) {
            $table->longText('face_photo')->nullable()->after('face_descriptor');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', fn(Blueprint $t) => $t->dropColumn('face_photo'));
        Schema::table('gurus', fn(Blueprint $t) => $t->dropColumn('face_photo'));
    }
};
