<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Kop surat identitas sekolah untuk dokumen Asisten Guru (teks polos).
 * Sumber: Pengaturan → Identitas Sekolah. Tidak memakai kop HTML rapor.
 */
final class SchoolLetterhead
{
    /** @return list<string> */
    public static function lines(): array
    {
        // 1. Prioritaskan teks kop surat resmi yang sudah diatur/diunggah di aplikasi (Setting: kop_teks)
        $kopTeks = trim((string) Setting::get('kop_teks', ''));
        if ($kopTeks !== '') {
            $text = preg_replace('/<\/(p|div)>/i', "\n", $kopTeks);
            $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rawLines = array_map('trim', explode("\n", $text));
            $parsedLines = array_values(array_filter($rawLines, fn (string $l) => $l !== ''));
            if (! empty($parsedLines)) {
                return $parsedLines;
            }
        }

        // 2. Fallback jika kop_teks belum diatur: susun dari identitas sekolah di Setting
        $nama = self::schoolName();
        $alamat = trim((string) Setting::get('alamat_sekolah', ''));
        $kota = trim((string) Setting::get('kota', ''));
        $provinsi = trim((string) Setting::get('provinsi', ''));
        $telp = trim((string) Setting::get('telp_sekolah', ''));
        $npsn = trim((string) Setting::get('npsn', ''));

        $lines = [$nama];

        if ($alamat !== '') {
            $lines[] = $alamat;
        }

        $meta = [];
        if ($telp !== '') {
            $meta[] = 'Telp. '.$telp;
        }
        if ($npsn !== '') {
            $meta[] = 'NPSN '.$npsn;
        }

        $lokasi = implode(', ', array_values(array_filter([$kota, $provinsi], fn (string $v) => $v !== '')));
        if ($lokasi !== '') {
            $meta[] = $lokasi;
        }

        if ($meta !== []) {
            $lines[] = implode('  ·  ', $meta);
        }

        return $lines;
    }

    public static function asPlainText(): string
    {
        return implode("\n", self::lines());
    }

    /** Blok instruksi untuk system/prompt AI. */
    public static function asPromptBlock(): string
    {
        return "KOP SURAT WAJIB (salin PERSIS di baris paling atas setiap jawaban; jangan diganti atau dikarang):\n"
            .self::asPlainText();
    }

    public static function schoolName(): string
    {
        $nama = trim((string) Setting::get('nama_sekolah', 'Sekolah'));

        return $nama !== '' ? $nama : 'Sekolah';
    }

    public static function kepalaSekolah(): string
    {
        $kepalaGuru = null;
        try {
            if (class_exists(\App\Models\Guru::class)) {
                $kepalaGuru = \App\Models\Guru::whereHas('user', fn ($q) => $q->where('access', 'kepala'))->first();
            }
        } catch (\Throwable $e) {
        }

        if ($kepalaGuru && ! empty($kepalaGuru->nama)) {
            return $kepalaGuru->nama;
        }

        $nama = trim((string) Setting::get('kepala_sekolah', ''));

        return $nama !== '' ? $nama : '[Nama Kepala Sekolah]';
    }

    public static function nipKepala(): string
    {
        $kepalaGuru = null;
        try {
            if (class_exists(\App\Models\Guru::class)) {
                $kepalaGuru = \App\Models\Guru::whereHas('user', fn ($q) => $q->where('access', 'kepala'))->first();
            }
        } catch (\Throwable $e) {
        }

        if ($kepalaGuru && (! empty($kepalaGuru->nik) || ! empty($kepalaGuru->nip))) {
            return (string) ($kepalaGuru->nik ?: $kepalaGuru->nip);
        }

        $nip = trim((string) Setting::get('nip_kepala', ''));

        return $nip !== '' ? $nip : '......................';
    }

    /**
     * Stempel identitas + sumber digital untuk hasil scan buku (OCR).
     * Pertanggungjawaban anti-plagiarisme: jelas bahwa teks berasal dari
     * fotografi halaman lewat Asisten Guru SIMS, bukan karya orisinal AI/guru.
     *
     * @param  array{pages?:int, recorded_at?:string}  $meta
     */
    public static function ocrScanStamp(array $meta = []): string
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $recorded = trim((string) ($meta['recorded_at'] ?? ''));
        if ($recorded === '') {
            $recorded = now()->timezone($timezone)->format('d/m/Y H:i T');
        }

        $pages = isset($meta['pages']) ? max(1, (int) $meta['pages']) : 1;
        $pageLine = $pages > 1
            ? "Jumlah halaman difoto: {$pages}"
            : 'Jumlah halaman difoto: 1';

        $sep = str_repeat('═', 42);
        $lines = array_merge(
            self::lines(),
            [
                $sep,
                'SUMBER DIGITAL · SCAN BUKU',
                'Asisten Guru SIMS (B\'tive) · trademark sekolah',
                'Teks diekstrak dari foto halaman buku/materi cetak.',
                'Bukan karya orisinal AI. Gunakan untuk pembelajaran internal.',
                'Saat mengutip, cantumkan judul/penulis buku asli.',
                $pageLine,
                'Dicatat sistem: '.$recorded,
                $sep,
            ],
        );

        return implode("\n", $lines);
    }

    /**
     * Prefiks kop + stempel sumber scan pada teks OCR (hindari dobel stempel).
     *
     * @param  array{pages?:int, recorded_at?:string}  $meta
     */
    public static function ensureOcrAttribution(string $body, array $meta = []): string
    {
        $body = preg_replace("/\r\n?/", "\n", $body) ?? $body;
        $body = trim($body);
        $stamp = self::ocrScanStamp($meta);

        if ($body === '') {
            return $stamp;
        }

        $upper = mb_strtoupper($body);
        // Sudah distempel (history / OCR ulang) — jangan dobel.
        if (str_contains($upper, 'SUMBER DIGITAL') && str_contains($upper, 'SCAN BUKU')) {
            $firstLine = trim(explode("\n", $body, 2)[0] ?? '');
            if ($firstLine !== '' && mb_strtoupper($firstLine) === mb_strtoupper(self::schoolName())) {
                return $body;
            }

            return self::asPlainText()."\n\n".$body;
        }

        return $stamp."\n\n".$body;
    }

    /**
     * Pastikan teks diawali kop sekolah dari Setting.
     * Jika model sudah menulis nama sekolah yang benar, biarkan.
     * Jika ada kop lama/asing di atas, diganti.
     */
    public static function ensurePrefix(string $body): string
    {
        $body = preg_replace("/\r\n?/", "\n", $body) ?? $body;
        $body = trim($body);
        $kop = self::asPlainText();

        if ($body === '') {
            return $kop;
        }

        // Cocokkan baris pertama saja (bukan prefix seluruh body) agar
        // fallback nama "Sekolah" tidak menahan teks yang kebetulan diawali kata itu.
        $firstLine = trim(explode("\n", $body, 2)[0] ?? '');
        $lines = self::lines();
        $firstKopLine = $lines[0] ?? self::schoolName();
        $nama = self::schoolName();

        if ($firstLine !== '' && (mb_strtoupper($firstLine) === mb_strtoupper($firstKopLine) || mb_strtoupper($firstLine) === mb_strtoupper($nama))) {
            return $body;
        }

        return $kop."\n\n".self::stripLeadingForeignKop($body);
    }

    private static function stripLeadingForeignKop(string $body): string
    {
        // Judul bagian dokumen (bukan kop) — hentikan scan dan pertahankan dari baris ini.
        $contentMarkers = [
            'TUJUAN PEMBELAJARAN',
            'ALUR TUJUAN PEMBELAJARAN',
            'SOAL EVALUASI',
            'PERENCANAAN PEMBELAJARAN MENDALAM',
            'RANGKUMAN MATERI',
            'RINGKASAN MATERI',
            'CATATAN SISWA',
            'DRAF UMPAN BALIK',
            'DRAFT UMPAN BALIK',
            'UMPAN BALIK',
            'IDENTITAS',
            'TUJUAN',
            'LANGKAH',
            'PETUNJUK PENGERJAAN',
            'BAGIAN ',
            'BAGIAN A',
            'BAGIAN B',
            'KUNCI JAWABAN',
        ];

        $lines = explode("\n", $body);
        $cut = 0;
        $maxScan = min(12, count($lines));

        for ($i = 0; $i < $maxScan; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                $cut = $i + 1;

                continue;
            }

            $upper = mb_strtoupper($line);
            foreach ($contentMarkers as $marker) {
                if (str_starts_with($upper, $marker)) {
                    return trim(implode("\n", array_slice($lines, $i)));
                }
            }

            // Hanya pola kop/lembaga — jangan anggap HURUF KAPITAL generik (judul Nalar) sebagai kop.
            // Pakai \b pada akronim pendek agar "MA" tidak memakan "MATERI", "SD" tidak memakan "SEDERHANA", dll.
            if (preg_match('/^(YAYASAN|SMP\b|SMA\b|SMK\b|SD\b|MI\b|MTS\b|MA\b|TERAKREDITASI|JL\.|JALAN|TELP\b|TELEPON|EMAIL|WEBSITE|NPSN\b|KOMP\.)/u', $upper)) {
                $cut = $i + 1;

                continue;
            }

            break;
        }

        return trim(implode("\n", array_slice($lines, $cut)));
    }

    /** HTML kop surat bersih yang sudah diatur di Pengaturan (Setting: kop_teks). */
    public static function kopTeksHtml(): string
    {
        $html = trim((string) Setting::get('kop_teks', ''));
        if ($html !== '') {
            return \App\Support\RichText::clean($html);
        }

        $nama = self::schoolName();
        $alamat = trim((string) Setting::get('alamat_sekolah', ''));
        $kota = trim((string) Setting::get('kota', ''));
        $provinsi = trim((string) Setting::get('provinsi', ''));
        $telp = trim((string) Setting::get('telp_sekolah', ''));
        $npsn = trim((string) Setting::get('npsn', ''));

        $sub = [];
        if ($alamat !== '') {
            $sub[] = $alamat;
        }
        if ($telp !== '') {
            $sub[] = 'Telp. '.$telp;
        }
        if ($npsn !== '') {
            $sub[] = 'NPSN '.$npsn;
        }
        $lok = implode(', ', array_values(array_filter([$kota, $provinsi], fn ($v) => $v !== '')));
        if ($lok !== '') {
            $sub[] = $lok;
        }

        $subText = implode('  &middot;  ', $sub);

        $out = '<h2 style="font-family: inherit; font-size: 16px; font-weight: bold; margin: 0; padding: 0;">'.e($nama).'</h2>';
        if ($subText !== '') {
            $out .= '<p style="font-family: inherit; font-size: 11px; margin: 2px 0 0; padding: 0;">'.e($subText).'</p>';
        }

        return $out;
    }

    /** Path berkas logo kiri di storage / public (untuk DomPDF atau Word). */
    public static function logoKiriPath(): ?string
    {
        $v = Setting::get('kop_logo_kiri');
        if ($v && \Illuminate\Support\Facades\Storage::disk('public')->exists($v)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->path($v);
        }
        if (file_exists(public_path('img/tutwuri.png'))) {
            return public_path('img/tutwuri.png');
        }

        return null;
    }

    /** Path berkas logo kanan di storage / public. */
    public static function logoKananPath(): ?string
    {
        $v = Setting::get('kop_logo_kanan');
        if ($v && \Illuminate\Support\Facades\Storage::disk('public')->exists($v)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->path($v);
        }

        $logo = Setting::get('sekolah_logo');
        if ($logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->path($logo);
        }

        if (file_exists(public_path('img/maitreyawira_square.png'))) {
            return public_path('img/maitreyawira_square.png');
        }

        if (file_exists(public_path('img/logo.png'))) {
            return public_path('img/logo.png');
        }

        return null;
    }

    /** URL web logo kiri untuk tag <img> di layar browser. */
    public static function logoKiriUrl(): ?string
    {
        $v = Setting::get('kop_logo_kiri');
        if ($v && \Illuminate\Support\Facades\Storage::disk('public')->exists($v)) {
            return asset('storage/'.$v);
        }
        if (file_exists(public_path('img/tutwuri.png'))) {
            return asset('img/tutwuri.png');
        }

        return null;
    }

    /** URL web logo kanan untuk tag <img> di layar browser. */
    public static function logoKananUrl(): ?string
    {
        $v = Setting::get('kop_logo_kanan');
        if ($v && \Illuminate\Support\Facades\Storage::disk('public')->exists($v)) {
            return asset('storage/'.$v);
        }

        $logo = Setting::get('sekolah_logo');
        if ($logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            return asset('storage/'.$logo);
        }

        if (file_exists(public_path('img/maitreyawira_square.png'))) {
            return asset('img/maitreyawira_square.png');
        }

        if (file_exists(public_path('img/logo.png'))) {
            return asset('img/logo.png');
        }

        return null;
    }

    /** Data URI base64 logo kiri (paling andal untuk DomPDF tanpa kendala network/chroot). */
    public static function logoKiriDataUri(): ?string
    {
        return self::fileToDataUri(self::logoKiriPath());
    }

    /** Data URI base64 logo kanan. */
    public static function logoKananDataUri(): ?string
    {
        return self::fileToDataUri(self::logoKananPath());
    }

    /**
     * Render blok kop surat lengkap (logo kiri, teks kop HTML, logo kanan, dan garis ganda)
     * yang dioptimalkan untuk PDF engine (DomPDF).
     */
    public static function renderHtmlForPdf(): string
    {
        $logoKiri = self::logoKiriDataUri();
        $logoKanan = self::logoKananDataUri();
        $kopHtml = self::kopTeksHtml();

        $leftImg = $logoKiri ? '<img src="'.$logoKiri.'" alt="Logo Kiri" style="max-height: 52px; max-width: 52px;">' : '';
        $rightImg = $logoKanan ? '<img src="'.$logoKanan.'" alt="Logo Kanan" style="max-height: 52px; max-width: 52px;">' : '';

        return '
        <div style="width: 100%; margin-bottom: 6px;">
            <table style="width: 100%; border-collapse: collapse; border: none !important; margin-bottom: 3px;">
                <tr>
                    <td style="width: 60px; text-align: left; vertical-align: middle; border: none !important; padding: 0 4px;">
                        '.$leftImg.'
                    </td>
                    <td style="text-align: center; vertical-align: middle; border: none !important; padding: 0 6px;">
                        <div style="text-align: center; line-height: 1.25; color: #000000;">
                            '.$kopHtml.'
                        </div>
                    </td>
                    <td style="width: 60px; text-align: right; vertical-align: middle; border: none !important; padding: 0 4px;">
                        '.$rightImg.'
                    </td>
                </tr>
            </table>
            <div style="border-bottom: 3px double #000000; width: 100%; margin-top: 3px; margin-bottom: 6px;"></div>
        </div>';
    }

    private static function fileToDataUri(?string $path): ?string
    {
        if (! $path || ! is_file($path)) {
            return null;
        }

        $mime = @mime_content_type($path) ?: 'image/png';
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
