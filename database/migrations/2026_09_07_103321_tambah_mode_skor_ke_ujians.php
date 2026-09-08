<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ujians', function (Blueprint $table) {
            $table->string('mode_skor', 16)->default('rata_rata')->after('target_nilai');
        });
    }

    public function down(): void
    {
        Schema::table('ujians', function (Blueprint $table) {
            $table->dropColumn('mode_skor');
        });
    }
};
