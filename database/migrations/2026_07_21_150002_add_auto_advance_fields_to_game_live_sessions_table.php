<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-advance live: kolom "deadline" soal aktif (dari time_limit_seconds soal) supaya sesi bisa
 * otomatis maju kalau waktu habis, dan "phase_started_at" (generik dipakai utk fase reveal &
 * standings) supaya sesi bisa otomatis maju setelah beberapa detik tanpa perlu host mengklik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_live_sessions', function (Blueprint $table) {
            $table->timestamp('question_deadline_at')->nullable()->after('question_started_at');
            $table->timestamp('phase_started_at')->nullable()->after('question_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_live_sessions', function (Blueprint $table) {
            $table->dropColumn(['question_deadline_at', 'phase_started_at']);
        });
    }
};
