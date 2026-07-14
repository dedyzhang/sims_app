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

        // Visibilitas tiap bagian panduan mengikuti izin RBAC yang SAMA dgn yang menggerbang
        // menu/fitur aslinya (lihat layouts/app.blade.php & routes/web.php), bukan daftar role
        // hardcode — supaya kalau admin mengubah RolePermission lewat Pengaturan > Hak Akses,
        // panduan yang tampil ikut menyesuaikan otomatis.
        $sections = array_values(array_filter($sections, function($section) use ($user, $isAdmin, $isGuru, $isWali, $isSiswa, $isOrtu) {
            $title = strtolower($section['title']);

            if (str_contains($title, 'data master')) return $user->canAccess('manage_users');
            if (str_contains($title, 'sistem dan pengaturan')) return $user->canAccess('manage_settings');
            if (str_contains($title, 'kegiatan harian')) return $isAdmin;
            if (str_contains($title, 'checklist agar tidak terlewat')) return $isAdmin;
            if (str_contains($title, 'catatan tinjauan teknis')) return $isAdmin;

            // manage_absensi = boleh kelola absensi semua kelas; wali kelas boleh kelola kelasnya
            // sendiri; siswa/ortu boleh lihat riwayat absensi diri sendiri — ketiganya perlu tetap
            // melihat panduan ini walau bukan pemegang izin manage_absensi.
            if (str_contains($title, 'absensi dan presensi')) return $user->canAccess('manage_absensi') || $isWali || $isSiswa || $isOrtu;
            // 7 KAIH: siswa mengisi, wali kelas/admin melihat rekap, admin/pengelola KAIH mengatur soal.
            // Orang tua tidak punya menu KAIH → tidak perlu melihat panduannya.
            if (str_contains($title, '7 kaih')) return $isSiswa || $isWali || $isAdmin || $user->canAccess('manage_kaih');
            if (str_contains($title, 'agenda guru')) return $user->canAccess('manage_agenda') || $isGuru;
            // Agenda Rapat: semua guru/staf terkait boleh lihat; kelola penuh utk admin/pengelola rapat/sekretaris.
            if (str_contains($title, 'agenda rapat')) return $isGuru || $isAdmin || $user->canAccess('manage_rapat') || in_array($user->access, ['kesiswaan', 'sarpras', 'kurikulum', 'kepala'], true);
            if (str_contains($title, 'wali kelas')) return $isAdmin || $isWali;
            if (str_contains($title, 'sarana dan prasarana')) return $user->canAccess('manage_sarpras');
            if (str_contains($title, 'keuangan spp')) return $user->canAccess('manage_keuangan') || $isSiswa || $isOrtu;
            // Cetak Data route-nya admin-only (role:admin di routes/web.php), tidak ada RBAC lain.
            if (str_contains($title, 'cetak data')) return $isAdmin;
            if (str_contains($title, 'kartu pelajar digital')) return $isAdmin || $isSiswa || $isOrtu;

            return true;
        }));

        $ctx = [
            'access' => $access,
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
