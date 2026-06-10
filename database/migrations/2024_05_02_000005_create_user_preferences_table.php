<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('user_uuid', 36)->unique();
            // Tema warna utama
            $table->string('primary_color')->default('#4f46e5');   // indigo
            $table->string('secondary_color')->default('#7c3aed'); // violet
            $table->string('accent_color')->default('#06b6d4');    // cyan
            // Sidebar
            $table->string('sidebar_style')->default('default');   // default/compact/icon-only
            $table->string('sidebar_bg')->default('#1e293b');      // slate-800
            $table->string('sidebar_text')->default('#f1f5f9');
            // Layout
            $table->string('theme_mode')->default('light');        // light/dark
            $table->string('font_size')->default('md');            // sm/md/lg
            $table->boolean('compact_mode')->default(false);
            // Dashboard widgets (JSON array widget keys yang tampil)
            $table->json('dashboard_widgets')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
