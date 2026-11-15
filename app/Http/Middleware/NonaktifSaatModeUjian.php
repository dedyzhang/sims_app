<?php

namespace App\Http\Middleware;

use App\Support\ModeUjian;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode Ujian adalah saklar induk: modul yang dipasangi middleware ini mati total untuk
 * SEMUA peran selama Mode Ujian aktif (siswa sudah dialihkan lebih dulu oleh EnforceModeUjian).
 * Pakai: ->middleware(NonaktifSaatModeUjian::class.':Survei Pembelajaran')
 */
class NonaktifSaatModeUjian
{
    public function handle(Request $request, Closure $next, string $fitur = 'Fitur ini'): Response
    {
        if (ModeUjian::nonaktif()) {
            return $next($request);
        }

        $pesan = "{$fitur} dinonaktifkan selama Mode Ujian aktif.";

        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'mode_ujian' => true, 'message' => $pesan], 423);
        }

        return redirect()->route('dashboard')->with('info_mode_ujian', $pesan);
    }
}
