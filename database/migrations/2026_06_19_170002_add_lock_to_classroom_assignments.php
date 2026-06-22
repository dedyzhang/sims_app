<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Latihan/tugas terkunci: token + mode layar penuh (sama seperti materi). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_assignments', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('status');
            $table->string('access_token', 16)->nullable()->after('is_locked');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_assignments', function (Blueprint $table) {
            $table->dropColumn(['is_locked', 'access_token']);
        });
    }
};
