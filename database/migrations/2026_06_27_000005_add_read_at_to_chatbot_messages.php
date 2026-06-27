<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_messages', function (Blueprint $table) {
            // Tanda baca (read receipt). Null = belum dibaca penerima.
            $table->timestamp('read_at')->nullable()->after('matched_intent');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
