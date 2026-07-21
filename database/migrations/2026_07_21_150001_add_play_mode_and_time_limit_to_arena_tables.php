<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arena Belajar — host (guru/admin) memilih cara main kuis (solo saja / live saja / bebas),
 * dan tiap soal boleh punya batas waktu sendiri (dipakai saat mode live).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_quizzes', function (Blueprint $table) {
            $table->string('play_mode', 16)->default('bebas')->after('mode'); // solo|live|bebas
        });

        Schema::table('game_questions', function (Blueprint $table) {
            $table->unsignedInteger('time_limit_seconds')->nullable()->after('points');
        });
    }

    public function down(): void
    {
        Schema::table('game_questions', function (Blueprint $table) {
            $table->dropColumn('time_limit_seconds');
        });
        Schema::table('game_quizzes', function (Blueprint $table) {
            $table->dropColumn('play_mode');
        });
    }
};
