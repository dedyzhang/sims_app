<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            // Daftar blok dashboard yang disembunyikan user, mis. ["sebaran","quicklinks"]
            $table->json('dashboard_hidden')->nullable()->after('dashboard_layout');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn('dashboard_hidden');
        });
    }
};
