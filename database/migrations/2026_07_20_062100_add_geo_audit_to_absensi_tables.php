<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensis', function (Blueprint $table) {
            $table->decimal('geo_lat', 10, 7)->nullable()->after('keterangan');
            $table->decimal('geo_lng', 10, 7)->nullable()->after('geo_lat');
            $table->unsignedSmallInteger('geo_accuracy')->nullable()->after('geo_lng');
            $table->unsignedSmallInteger('geo_jarak')->nullable()->after('geo_accuracy');
        });

        Schema::table('presensi_gurus', function (Blueprint $table) {
            $table->decimal('geo_lat_masuk', 10, 7)->nullable()->after('keterangan');
            $table->decimal('geo_lng_masuk', 10, 7)->nullable()->after('geo_lat_masuk');
            $table->unsignedSmallInteger('geo_accuracy_masuk')->nullable()->after('geo_lng_masuk');
            $table->unsignedSmallInteger('geo_jarak_masuk')->nullable()->after('geo_accuracy_masuk');
            $table->decimal('geo_lat_pulang', 10, 7)->nullable()->after('geo_jarak_masuk');
            $table->decimal('geo_lng_pulang', 10, 7)->nullable()->after('geo_lat_pulang');
            $table->unsignedSmallInteger('geo_accuracy_pulang')->nullable()->after('geo_lng_pulang');
            $table->unsignedSmallInteger('geo_jarak_pulang')->nullable()->after('geo_accuracy_pulang');
        });
    }

    public function down(): void
    {
        Schema::table('absensis', function (Blueprint $table) {
            $table->dropColumn(['geo_lat', 'geo_lng', 'geo_accuracy', 'geo_jarak']);
        });

        Schema::table('presensi_gurus', function (Blueprint $table) {
            $table->dropColumn([
                'geo_lat_masuk', 'geo_lng_masuk', 'geo_accuracy_masuk', 'geo_jarak_masuk',
                'geo_lat_pulang', 'geo_lng_pulang', 'geo_accuracy_pulang', 'geo_jarak_pulang',
            ]);
        });
    }
};
