<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ganti daftar poin (child table) jadi satu konten rich-text (TinyMCE) langsung di app_updates. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_updates', function (Blueprint $table) {
            $table->text('content')->nullable()->after('title');
        });

        Schema::dropIfExists('app_update_items');
    }

    public function down(): void
    {
        Schema::create('app_update_items', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('update_id', 36);
            $table->text('teks');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('update_id');
            $table->foreign('update_id')->references('uuid')->on('app_updates')->onDelete('cascade');
        });

        Schema::table('app_updates', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
