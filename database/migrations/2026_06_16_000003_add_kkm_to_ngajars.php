<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KKTP (dulu KKM) per penugasan mengajar. Nullable → bila kosong,
     * fallback ke pelajarans.kkm lalu default 75.
     */
    public function up(): void
    {
        Schema::table('ngajars', function (Blueprint $table) {
            if (!Schema::hasColumn('ngajars', 'kkm')) {
                $table->unsignedTinyInteger('kkm')->nullable()->after('id_kelas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ngajars', function (Blueprint $table) {
            if (Schema::hasColumn('ngajars', 'kkm')) {
                $table->dropColumn('kkm');
            }
        });
    }
};
