<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_messages', function (Blueprint $table) {
            // Path relatif gambar terlampir (mis. "uploads/chat/xxx.jpg"). Null = pesan teks biasa.
            $table->string('attachment_path')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_messages', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }
};
