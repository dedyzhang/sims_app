<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Link Google Meet pada materi (kelas online). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_materials', function (Blueprint $table) {
            $table->string('meet_url', 300)->nullable()->after('link_url');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_materials', function (Blueprint $table) {
            $table->dropColumn('meet_url');
        });
    }
};
