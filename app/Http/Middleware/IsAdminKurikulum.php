<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdminKurikulum {
    public function handle(Request $request, Closure $next): Response {
        if (!auth()->check()) return redirect()->route('login');
        if (!in_array(auth()->user()->access, ['superadmin','admin','kurikulum'])) abort(403);
        return $next($request);
    }
}
