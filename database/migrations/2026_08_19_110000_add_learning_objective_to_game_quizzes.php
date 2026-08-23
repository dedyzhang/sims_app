<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_quizzes', function (Blueprint $table) {
            $table->text('learning_objective')->nullable()->after('instructions');
        });
    }

    public function down(): void
    {
        Schema::table('game_quizzes', function (Blueprint $table) {
            $table->dropColumn('learning_objective');
        });
    }
};
