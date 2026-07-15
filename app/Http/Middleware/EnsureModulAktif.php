<?php

namespace App\Http\Middleware;

use App\Support\ModulAktif;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blok akses URL bila modul dimatikan di Pengaturan Sistem → tab Fitur. */
class EnsureModulAktif
{
    public function handle(Request $request, Closure $next, string $modul): Response
    {
        ModulAktif::assertAktif($modul);

        return $next($request);
    }
}
