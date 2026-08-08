<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sarpras_perbaikan', function (Blueprint $table) {
            if (! Schema::hasColumn('sarpras_perbaikan', 'nama_teknisi')) {
                $table->string('nama_teknisi', 120)->nullable()->after('teknisi_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sarpras_perbaikan', function (Blueprint $table) {
            $table->dropColumn('nama_teknisi');
        });
    }
};
