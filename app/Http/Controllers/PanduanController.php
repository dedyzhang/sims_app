<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class PanduanController extends Controller
{
    private const VISUAL = 'resources/panduan/visual.html';

    public function visual(): View
    {
        abort_unless(File::exists(base_path(self::VISUAL)), 404, 'Panduan visual belum tersedia.');

        return view('panduan.visual');
    }

    public function content(): Response
    {
        $path = base_path(self::VISUAL);

        abort_unless(File::exists($path), 404, 'Panduan visual belum tersedia.');

        $user = auth()->user();
        $isAdmin = $user->isAdmin();
        // Wali kelas = punya penugasan homeroom (tabel walikelas), BUKAN literal access==='walikelas' —
        // seorang guru dgn access lain (mis. kesiswaan) tetap bisa jadi wali kelas satu kelas.
        $isWali  = (bool) $user->guru?->walikelas;
        $isGuru  = (bool) $user->guru;
        $isSiswa = $user->access === 'siswa' || $user->siswa;
        $isOrtu  = $user->access === 'orangtua';

        $ctx = [
            'access' => $user->access,
            'label' => $user->roleLabel(),
            'isWali' => (bool) $user->guru?->walikelas,
            'isAdmin' => $user->isAdmin(),
        ];

        $html = (string) File::get($path);
        $inject = '<script>window.PANDUAN_CTX='.json_encode($ctx, JSON_UNESCAPED_UNICODE).';</script>';
        if (str_contains($html, '</head>')) {
            $html = str_replace('</head>', $inject."\n</head>", $html);
        } else {
            $html = $inject.$html;
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, max-age=60',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
