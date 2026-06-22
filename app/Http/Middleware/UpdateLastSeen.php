<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memperbarui users.last_seen_at untuk presence Forum.
 * Dithrottle: hanya menulis bila > 60 detik dari update terakhir. Hanya user login.
 * Update via query (tanpa event / tanpa menyentuh updated_at) agar ringan.
 */
class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (!$user->last_seen_at || $user->last_seen_at->lt(now()->subSeconds(60)))) {
            DB::table('users')->where('uuid', $user->uuid)->update(['last_seen_at' => now()]);
            $user->last_seen_at = now();
        }

        return $next($request);
    }
}
