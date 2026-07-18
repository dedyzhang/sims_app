<?php

namespace App\Http\Middleware;

use App\Support\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // superadmin bisa akses segalanya
        if (UserRole::canonicalize((string) $user->access) === 'superadmin') {
            return $next($request);
        }

        if (! UserRole::matches((string) $user->access, ...$roles)) {
            abort(403, 'Akses tidak diizinkan.');
        }

        return $next($request);
    }
}
