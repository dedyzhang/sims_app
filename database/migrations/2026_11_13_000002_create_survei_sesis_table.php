<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survei_periodes', function (Blueprint $table) {
            $table->boolean('wajib_token')->default(false);
        });

        Schema::create('survei_sesis', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('school_id')->index();
            $table->uuid('periode_id');
            $table->json('kelas_ids');
            $table->text('token');
            $table->dateTime('berlaku_sampai');
            $table->dateTime('diakhiri_pada')->nullable();
            $table->timestamps();
            $table->foreign('periode_id')->references('uuid')->on('survei_periodes')->cascadeOnDelete();
            $table->index(['periode_id', 'diakhiri_pada', 'berlaku_sampai']);
        });

        DB::table('survei_periodes')->where('ditutup', false)->where('selesai', '>=', now())
            ->update(['wajib_token' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('survei_sesis');
        Schema::table('survei_periodes', function (Blueprint $table) {
            $table->dropColumn('wajib_token');
        });
    }
};
