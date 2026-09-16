<?php

namespace App\Http\Middleware;

use App\Models\DemoAccess;
use App\Services\DemoCallbackClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnforceDemoAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || blank($user->demo_access_id)) {
            return $next($request);
        }

        $route = $request->route()?->getName();
        if (in_array($route, ['login', 'login.post', 'login.pin', 'logout', 'demo.berakhir'], true)) {
            return $next($request);
        }

        $access = $user->demoAccess;
        if ($access && $access->isCurrentlyActive()) {
            return $next($request);
        }

        if ($access && $access->status === DemoAccess::STATUS_ACTIVE) {
            if ($access->markExpiredAndInvalidateSessions()) {
                app(DemoCallbackClient::class)->notify($access->fresh() ?? $access, 'access.expired');
            }
        } elseif ($access) {
            // Sudah revoked/expired: penyapuan sesi mahal (driver file memindai
            // seluruh direktori) dan jalur ini kena tiap request user tsb. Sapu
            // sekali per menit saja — Auth::logout() di bawah sudah mengurus
            // sesi request ini sendiri.
            if (Cache::add('demo:sessions-purged:'.$access->id, 1, 60)) {
                $access->invalidateSessions();
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Masa akses demo telah berakhir.'], 403);
        }

        return redirect()->route('demo.berakhir');
    }
}
