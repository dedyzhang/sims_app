<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pembatas akses widget chat: SEMUA role pengguna (siswa, orang tua, guru,
 * walikelas, waka/kurikulum/kesiswaan/sapras, kepala, sekretaris, dll) boleh
 * memakai widget penanya untuk menghubungi admin.
 *
 * Hanya superadmin/admin yang dikecualikan — mereka memegang Inbox
 * (lihat middleware isAdmin), bukan widget penanya ini.
 */
class CanUseChatbot
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (in_array($user->access, ['superadmin', 'admin'], true)) {
            abort(403, 'Admin menggunakan Inbox chat, bukan widget penanya.');
        }

        return $next($request);
    }
}
