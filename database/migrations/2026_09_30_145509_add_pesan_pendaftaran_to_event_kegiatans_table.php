<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_kegiatans', function (Blueprint $table) {
            $table->text('pesan_pendaftaran')->nullable()->after('deskripsi');
        });
    }

    public function down(): void
    {
        Schema::table('event_kegiatans', function (Blueprint $table) {
            $table->dropColumn('pesan_pendaftaran');
        });
    }
};
