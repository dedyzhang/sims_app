<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_admin_settings', function (Blueprint $table) {
            // Notifikasi pesan masuk per percakapan (highlight "pesan baru" + audio).
            $table->boolean('message_notif_enabled')->default(true)->after('sound_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_admin_settings', function (Blueprint $table) {
            $table->dropColumn('message_notif_enabled');
        });
    }
};
