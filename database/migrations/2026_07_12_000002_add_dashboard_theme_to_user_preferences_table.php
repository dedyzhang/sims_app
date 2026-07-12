<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('user_preferences', 'dashboard_theme')) {
                $table->string('dashboard_theme')->default('windows11')->after('ui_style');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            if (Schema::hasColumn('user_preferences', 'dashboard_theme')) {
                $table->dropColumn('dashboard_theme');
            }
        });
    }
};