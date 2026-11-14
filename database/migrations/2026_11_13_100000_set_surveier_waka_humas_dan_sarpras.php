<?php

use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['humas', 'sarpras'] as $role) {
            RolePermission::firstOrCreate([
                'role' => $role,
                'permission' => 'jadi_surveier',
            ]);
        }

        RolePermission::clearCache();
    }

    public function down(): void
    {
        RolePermission::query()
            ->where('permission', 'jadi_surveier')
            ->whereIn('role', ['humas', 'sarpras'])
            ->delete();

        RolePermission::clearCache();
    }
};
