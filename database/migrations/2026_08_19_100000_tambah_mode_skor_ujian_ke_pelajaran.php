<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pelajarans', function (Blueprint $table) {
            $table->string('mode_skor_ujian', 16)->default('rata_rata')->after('kkm');
        });
    }

    public function down(): void
    {
        Schema::table('pelajarans', function (Blueprint $table) {
            $table->dropColumn('mode_skor_ujian');
        });
    }
};
