<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Pengecekan RBAC yang sudah terpusat di User model (mencakup pengecualian superadmin)
        if (!$user->canAccess($permission)) {
            abort(403, 'AKSES TIDAK DIIZINKAN. Peran Anda belum diberi izin untuk fitur ini.');
        }

        return $next($request);
    }
}
