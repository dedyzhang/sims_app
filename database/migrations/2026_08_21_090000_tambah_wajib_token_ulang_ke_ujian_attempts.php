<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_attempts', function (Blueprint $table) {
            $table->boolean('wajib_token_ulang')->default(false)->after('dikunci');
        });
    }

    public function down(): void
    {
        Schema::table('ujian_attempts', function (Blueprint $table) {
            $table->dropColumn('wajib_token_ulang');
        });
    }
};
