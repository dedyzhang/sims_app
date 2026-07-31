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
        // Delete existing data as the structure is completely changing
        \Illuminate\Support\Facades\DB::table('jadwal_piket')->truncate();

        Schema::table('jadwal_piket', function (Blueprint $table) {
            $table->dropIndex(['id_guru', 'tanggal']);
            $table->dropIndex(['tanggal']);
            
            $table->dropColumn(['tanggal', 'semester', 'status']);
            $table->unsignedTinyInteger('hari')->after('id_guru')->comment('1=Senin, 7=Minggu');
            
            $table->unique(['id_guru', 'hari']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('jadwal_piket')->truncate();
        
        Schema::table('jadwal_piket', function (Blueprint $table) {
            $table->dropUnique(['id_guru', 'hari']);
            $table->dropColumn('hari');
            
            $table->date('tanggal');
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('status', 20)->default('aktif');
            
            $table->index(['id_guru', 'tanggal']);
            $table->index('tanggal');
        });
    }
};
