<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survei_periodes', function (Blueprint $table) {
            $table->boolean('wajib_kios')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('survei_periodes', function (Blueprint $table) {
            $table->dropColumn('wajib_kios');
        });
    }
};
