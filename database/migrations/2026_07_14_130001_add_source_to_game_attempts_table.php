<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Isolasi attempt live vs async (cegah reuse kunci reveal untuk submit async). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_attempts', function (Blueprint $table) {
            $table->string('source', 16)->default('async')->after('student_id');
        });

        Schema::table('game_attempts', function (Blueprint $table) {
            $table->dropForeign(['assignment_id']);
            $table->dropUnique(['assignment_id', 'student_id']);
            
            $table->unique(['assignment_id', 'student_id', 'source']);
            $table->foreign('assignment_id')->references('uuid')->on('game_quiz_assignments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_attempts', function (Blueprint $table) {
            $table->dropForeign(['assignment_id']);
            $table->dropUnique(['assignment_id', 'student_id', 'source']);
            
            $table->unique(['assignment_id', 'student_id']);
            $table->foreign('assignment_id')->references('uuid')->on('game_quiz_assignments')->cascadeOnDelete();
            
            $table->dropColumn('source');
        });
    }
};
