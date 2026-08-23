<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_teacher_audio_assets', function (Blueprint $table) {
            $table->string('voice_gender', 20)->default('wanita')->after('voice');
            $table->string('vibe', 30)->default('ceria')->after('voice_gender');
            $table->unsignedSmallInteger('tempo_percent')->default(100)->after('vibe');
            $table->index(['voice_gender', 'vibe', 'tempo_percent']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_teacher_audio_assets', function (Blueprint $table) {
            $table->dropIndex(['voice_gender', 'vibe', 'tempo_percent']);
            $table->dropColumn(['voice_gender', 'vibe', 'tempo_percent']);
        });
    }
};