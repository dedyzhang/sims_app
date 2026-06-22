<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_material_links', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false);
            $table->string('access_token', 16)->nullable();
        });

        Schema::table('classroom_assignment_links', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false);
            $table->string('access_token', 16)->nullable();
        });

        // Migrate existing values
        $materials = DB::table('classroom_materials')->where('is_locked', true)->get();
        foreach ($materials as $m) {
            DB::table('classroom_material_links')
                ->where('material_id', $m->uuid)
                ->update(['is_locked' => true, 'access_token' => $m->access_token]);
        }

        $assignments = DB::table('classroom_assignments')->where('is_locked', true)->get();
        foreach ($assignments as $a) {
            DB::table('classroom_assignment_links')
                ->where('assignment_id', $a->uuid)
                ->update(['is_locked' => true, 'access_token' => $a->access_token]);
        }
    }

    public function down(): void
    {
        Schema::table('classroom_material_links', function (Blueprint $table) {
            $table->dropColumn(['is_locked', 'access_token']);
        });

        Schema::table('classroom_assignment_links', function (Blueprint $table) {
            $table->dropColumn(['is_locked', 'access_token']);
        });
    }
};
