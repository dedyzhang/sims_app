<?php

namespace App\Http\Controllers;

use App\Models\ForumRolePermission;
use App\Models\ForumTopic;
use App\Support\Forum;
use Illuminate\Http\Request;

/**
 * "Pengaturan Akses Forum" — matriks role × permission yang dapat diubah admin.
 * Perubahan disimpan ke forum_role_permissions; Policy membacanya saat runtime.
 * Hanya untuk user dengan permission forum.manage_access.
 */
class ForumAccessController extends Controller
{
    public function edit()
    {
        $this->authorize('manageAccess', ForumTopic::class);

        return view('forum.access', [
            'roles'       => Forum::ROLE_LABELS,
            'permissions' => Forum::PERMISSIONS,
            'matrix'      => ForumRolePermission::matrix(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('manageAccess', ForumTopic::class);

        $checked = $request->input('perm', []); // perm[role][permission] = '1'

        foreach (array_keys(Forum::ROLE_LABELS) as $role) {
            foreach (array_keys(Forum::PERMISSIONS) as $perm) {
                $allowed = isset($checked[$role][$perm]);
                ForumRolePermission::updateOrCreate(
                    ['access' => $role, 'permission' => $perm],
                    ['allowed' => $allowed]
                );
            }
        }

        ForumRolePermission::clearCache();
        Forum::audit('update_access');

        return back()->with('success', 'Matriks akses forum disimpan.');
    }
}
