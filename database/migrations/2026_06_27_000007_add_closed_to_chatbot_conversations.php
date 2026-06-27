<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak penutupan percakapan: kapan & oleh admin mana ditutup.
 * Pesan TIDAK pernah dihapus — percakapan tertutup tetap tersimpan sebagai histori/bukti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_conversations', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('started_at');
            $table->uuid('closed_by')->nullable()->after('closed_at');

            $table->foreign('closed_by')->references('uuid')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_conversations', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropColumn(['closed_at', 'closed_by']);
        });
    }
};
