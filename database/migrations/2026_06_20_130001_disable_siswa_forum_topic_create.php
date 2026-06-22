<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ForumRolePermission;

return new class extends Migration
{
    public function up(): void
    {
        ForumRolePermission::where('access', 'siswa')
            ->where('permission', 'forum.topic.create')
            ->update(['allowed' => false]);

        ForumRolePermission::clearCache();
    }

    public function down(): void
    {
        ForumRolePermission::where('access', 'siswa')
            ->where('permission', 'forum.topic.create')
            ->update(['allowed' => true]);

        ForumRolePermission::clearCache();
    }
};
