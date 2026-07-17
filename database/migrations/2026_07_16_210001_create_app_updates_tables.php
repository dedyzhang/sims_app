<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pengumuman "apa yang baru" per versi — ditampilkan sebagai popup sekali per sesi login. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_updates', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('version', 30);
            $table->string('title', 150);
            $table->date('released_at');
            $table->boolean('is_published')->default(true);
            $table->string('created_by', 36)->nullable();
            $table->timestamps();
            $table->index('is_published');
        });

        Schema::create('app_update_items', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('update_id', 36);
            $table->text('teks');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('update_id');

            $table->foreign('update_id')->references('uuid')->on('app_updates')->onDelete('cascade');
        });

        // Dilacak per-user: versi update terakhir yang sudah ditandai "jangan tampilkan lagi".
        Schema::table('users', function (Blueprint $table) {
            $table->string('dismissed_update_id', 36)->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dismissed_update_id');
        });
        Schema::dropIfExists('app_update_items');
        Schema::dropIfExists('app_updates');
    }
};
