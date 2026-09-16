<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_reset_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('manifest_version', 16);
            $table->string('status', 32)->index();
            $table->boolean('dry_run')->default(false);
            $table->json('table_counts')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_reset_runs');
    }
};
