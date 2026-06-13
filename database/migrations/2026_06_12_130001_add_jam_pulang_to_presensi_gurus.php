<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi_gurus', function (Blueprint $table) {
            $table->time('jam_pulang')->nullable()->after('jam_masuk');
        });
    }

    public function down(): void
    {
        Schema::table('presensi_gurus', function (Blueprint $table) {
            $table->dropColumn('jam_pulang');
        });
    }
};
