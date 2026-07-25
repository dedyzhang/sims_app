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
        $nama = trim((string) Setting::get('kepala_sekolah', ''));

        return $nama !== '' ? $nama : '[Nama Kepala Sekolah]';
    }

    public static function nipKepala(): string
    {
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
     * Buang kop + stempel SUMBER DIGITAL · SCAN BUKU dari teks OCR.
     * Dipakai saat generate soal/RPM: model hanya butuh isi materi buku,
     * bukan header anti-plagiarisme (stempel tetap di Hasil/History/export).
     */
    public static function stripOcrAttribution(string $text): string
    {
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $upper = mb_strtoupper($text);
        if (! str_contains($upper, 'SUMBER DIGITAL') || ! str_contains($upper, 'SCAN BUKU')) {
            return $text;
        }

        // Body biasanya setelah baris ═ penutup blok stempel.
        if (preg_match('/SUMBER DIGITAL[^\n]*SCAN BUKU[\s\S]*?\n═{10,}\s*\n+/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
            $end = $m[0][1] + strlen($m[0][0]);
            $body = trim(substr($text, $end));
            if ($body !== '') {
                return $body;
            }
        }

        // Fallback: ambil setelah pemisah ═ kedua (kop…sep…stempel…sep…body).
        if (preg_match('/(?:^|\n)═{10,}\s*\n[\s\S]*?\n═{10,}\s*\n+([\s\S]+)$/u', $text, $m)) {
            $body = trim($m[1]);
            if ($body !== '') {
                return $body;
            }
        }

        return $text;
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
        $nama = self::schoolName();
        if ($firstLine !== '' && mb_strtoupper($firstLine) === mb_strtoupper($nama)) {
            return $body;
        }

        return $kop."\n\n".self::stripLeadingForeignKop($body);
    }

    private static function stripLeadingForeignKop(string $body): string
    {
        // Judul bagian dokumen (bukan kop) — hentikan scan dan pertahankan dari baris ini.
        $contentMarkers = [
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
}
