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
        Schema::table('pelajarans', function (Blueprint $table) {
            $table->unsignedTinyInteger('jp')->default(2)->after('kode'); // jam pelajaran / minggu
        });
    }

    public function down(): void
    {
        Schema::table('pelajarans', function (Blueprint $table) {
            $table->dropColumn('jp');
        });
    }
};
