<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsWaliSekredanGuru {
    public function handle(Request $request, Closure $next): Response {
        if (!auth()->check()) return redirect()->route('login');
        if (!in_array(auth()->user()->access, ['superadmin','admin','walikelas','sekretaris','guru','kurikulum'])) abort(403);
        return $next($request);
    }
}
