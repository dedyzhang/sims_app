<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration bawaan spatie/laravel-activitylog (create_activity_log_table) pakai
 * nullableMorphs() default -> subject_id/causer_id jadi unsignedBigInteger, cocok
 * utk model auto-increment. Tapi SEMUA model di app ini pakai UUID string sbg primary
 * key (User, GuruTidakHadir, dst — via HasUuids + $primaryKey='uuid'), bukan
 * auto-increment id. Di MySQL (strict mode aktif di hosting produksi), insert UUID ke
 * kolom bigint gagal dgn "SQLSTATE[01000]: Data truncated for column 'causer_id'" —
 * tak muncul di dev lokal krn SQLite tak menegakkan tipe kolom seketat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->string('subject_id', 36)->nullable()->change();
            $table->string('causer_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
            $table->unsignedBigInteger('causer_id')->nullable()->change();
        });
    }
};
