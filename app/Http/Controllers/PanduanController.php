<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PanduanController extends Controller
{
    private const SOURCE = 'docs/PANDUAN_PENGGUNAAN_SIMS_APP.md';

    public function index(): View
    {
        $path = base_path(self::SOURCE);

        abort_unless(File::exists($path), 404, 'Panduan penggunaan belum tersedia.');

        $markdown = (string) File::get($path);
        $parsed = $this->parseMarkdown($markdown);

        return view('panduan.index', [
            'docTitle' => $parsed['title'],
            'introHtml' => $parsed['introHtml'],
            'sections' => $parsed['sections'],
            'lastUpdated' => date('d M Y H:i', File::lastModified($path)),
        ]);
    }

    private function parseMarkdown(string $markdown): array
    {
        $markdown = preg_replace("/\r\n?/", "\n", $markdown) ?? $markdown;
        $title = 'Panduan Penggunaan SIMS';

        if (preg_match('/^#\s+(.+)$/m', $markdown, $match)) {
            $title = $this->plainHeading($match[1]);
        }

        preg_match_all('/^##\s+(.+)$/m', $markdown, $matches, PREG_OFFSET_CAPTURE);

        $sections = [];
        $usedSlugs = [];
        $introMarkdown = $markdown;

        if (!empty($matches[0])) {
            $introMarkdown = substr($markdown, 0, $matches[0][0][1]);

            foreach ($matches[0] as $index => $match) {
                $start = $match[1];
                $end = $matches[0][$index + 1][1] ?? strlen($markdown);
                $sectionMarkdown = trim(substr($markdown, $start, $end - $start));
                $headings = $this->headings($sectionMarkdown, $usedSlugs);
                $sectionTitle = $headings[0]['title'] ?? $this->plainHeading($matches[1][$index][0]);
                $sectionId = $headings[0]['id'] ?? $this->uniqueSlug($sectionTitle, $usedSlugs);
                $html = $this->markdownToHtml($sectionMarkdown, array_column($headings, 'id'));

                $sections[] = [
                    'id' => $sectionId,
                    'title' => $sectionTitle,
                    'html' => $html,
                    'search' => Str::lower($sectionTitle.' '.strip_tags($html)),
                    'subsections' => array_values(array_filter(
                        $headings,
                        fn (array $heading) => $heading['level'] === 3
                    )),
                ];
            }
        }

        $introMarkdown = trim(preg_replace('/^#\s+.+\n?/m', '', $introMarkdown, 1) ?? $introMarkdown);

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

        return [
            'title' => $title,
            'introHtml' => $introMarkdown !== '' ? $this->markdownToHtml($introMarkdown, []) : '',
            'sections' => $sections,
        ];
    }

    private function headings(string $markdown, array &$usedSlugs): array
    {
        preg_match_all('/^(#{2,3})\s+(.+)$/m', $markdown, $matches);

        $headings = [];

        foreach ($matches[1] as $index => $marks) {
            $title = $this->plainHeading($matches[2][$index]);
            $headings[] = [
                'level' => strlen($marks),
                'title' => $title,
                'id' => $this->uniqueSlug($title, $usedSlugs),
            ];
        }

        return $headings;
    }

    private function markdownToHtml(string $markdown, array $headingIds): string
    {
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $index = 0;

        return preg_replace_callback('/<h([23])>(.*?)<\/h\1>/s', function (array $match) use (&$index, $headingIds) {
            $id = $headingIds[$index] ?? null;
            $index++;

            if (!$id) {
                return $match[0];
            }

            return '<h'.$match[1].' id="'.e($id).'">'.$match[2].'</h'.$match[1].'>';
        }, $html) ?? $html;
    }

    private function plainHeading(string $heading): string
    {
        $heading = preg_replace('/\s+#+$/', '', trim($heading)) ?? trim($heading);
        $heading = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $heading) ?? $heading;
        $heading = str_replace(['`', '*', '_'], '', $heading);

        return trim($heading);
    }

    private function uniqueSlug(string $title, array &$usedSlugs): string
    {
        $base = Str::slug($title) ?: 'bagian';
        $slug = $base;
        $counter = 2;

        while (isset($usedSlugs[$slug])) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        $usedSlugs[$slug] = true;

        return $slug;
    }
}
