<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')
            ->where('permission', 'jadi_surveier')
            ->update(['permission' => 'jadi_surveyor']);
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->where('permission', 'jadi_surveyor')
            ->update(['permission' => 'jadi_surveier']);
    }
};
