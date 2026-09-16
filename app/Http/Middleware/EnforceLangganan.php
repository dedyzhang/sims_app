<?php

namespace App\Http\Middleware;

use App\Models\Langganan;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penegak lisensi langganan (titik integrasi 3, lihat PRD §10).
 *
 * Bila langganan kadaluarsa: seluruh pengguna NON-superadmin dialihkan ke
 * halaman "Langganan berakhir". Superadmin dikecualikan agar tetap bisa masuk
 * dan memperpanjang. Route login/logout/langganan tetap dapat diakses.
 * Tanpa baris langganan sama sekali → app tidak dikunci (instalasi lama/baru).
 */
class EnforceLangganan
{
    /** Route yang selalu boleh diakses meski lisensi kadaluarsa. */
    private const ROUTE_DIKECUALIKAN = [
        'login', 'login.post', 'login.pin', 'logout', 'password.request',
        'langganan.berakhir',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $langganan = Langganan::current();
        } catch (QueryException) {
            // Tabel langganan belum ada (migrasi belum jalan) → jangan kunci app.
            return $next($request);
        }

        if (! $langganan || ! $langganan->kadaluarsa()) {
            return $next($request);
        }

        $route = $request->route()?->getName();
        if ($route && (in_array($route, self::ROUTE_DIKECUALIKAN) || str_starts_with($route, 'langganan.'))) {
            return $next($request);
        }

        if (auth()->user()?->access === 'superadmin' || auth()->user()?->hasDemoAccess()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Langganan SIMS telah berakhir.'], 403);
        }

        return redirect()->route('langganan.berakhir');
    }
}
