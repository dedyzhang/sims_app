<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_comments', function (Blueprint $table) {
            $table->uuid('classroom_id')->nullable()->after('commentable_id');
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classroom_comments', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->dropColumn('classroom_id');
        });
    }
};
