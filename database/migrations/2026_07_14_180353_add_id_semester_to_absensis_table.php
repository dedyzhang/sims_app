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
        Schema::table('absensis', function (Blueprint $table) {
            $table->foreignId('id_semester')->nullable()->constrained('semesters')->nullOnDelete();
        });
        
        // Backfill existing records
        $activeSemester = \App\Models\Semester::where('aktif', true)->first();
        if ($activeSemester) {
            \App\Models\Absensi::whereNull('id_semester')->update(['id_semester' => $activeSemester->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('absensis', function (Blueprint $table) {
            $table->dropForeign(['id_semester']);
            $table->dropColumn('id_semester');
        });
    }
};
