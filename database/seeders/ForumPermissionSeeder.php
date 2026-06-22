<?php

namespace Database\Seeders;

use App\Models\ForumRolePermission;
use App\Support\Forum;
use Illuminate\Database\Seeder;

/**
 * Memetakan permission DEFAULT ke tiap role (kolom users.access).
 * Ini hanya nilai awal — admin dapat mengubahnya lewat "Pengaturan Akses Forum".
 */
class ForumPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(Forum::ROLE_LABELS) as $role) {
            $granted = Forum::DEFAULTS[$role] ?? [];
            foreach (array_keys(Forum::PERMISSIONS) as $perm) {
                ForumRolePermission::updateOrCreate(
                    ['access' => $role, 'permission' => $perm],
                    ['allowed' => in_array($perm, $granted, true)]
                );
            }
        }
        ForumRolePermission::clearCache();
    }
}
