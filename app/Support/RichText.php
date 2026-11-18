<?php

namespace App\Support;

/**
 * Pembersih HTML ringan untuk konten editor (TinyMCE). Penulis = guru/admin
 * (tepercaya), tapi tetap dibuang vektor XSS umum: <script>/<style>/<iframe>/
 * <object>, atribut on*, dan URI javascript:. Embed YouTube DISIMPAN sebagai
 * <div class="yt-embed" data-yt="ID"> (bukan iframe) lalu di-render saat tampil.
 * Rumus disimpan sebagai <img> SVG (data URI) — aman.
 */
class RichText
{
    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        // Buang tag berbahaya beserta isinya.
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta|base)\b[^>]*>.*?</\1>#is', '', $html);
        // Versi self-closing / tanpa penutup.
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta|base)\b[^>]*/?>#is', '', $html);
        // Atribut event handler: on...="..."
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        // URI berbahaya di href/src.
        $html = preg_replace('#\s(href|src)\s*=\s*("|\')\s*(javascript|vbscript|data\s*:\s*text/html)[^"\']*\2#i', ' $1=$2#$2', $html);

        return trim($html);
    }

    /**
     * Konversi HTML ke teks polos yang bersih dari tag dan entitas HTML (seperti &nbsp;).
     * Cocok untuk cuplikan, ringkasan, atau ekspor teks biasa.
     */
    public static function toPlainText(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        // Pertahankan representasi LaTeX dari rumus matematika jika ada
        $html = preg_replace('/<img[^>]+class=["\'][^"\']*math-svg[^"\']*["\'][^>]+data-latex=["\']([^"\']+)["\'][^>]*>/i', ' $1 ', $html);
        $html = preg_replace('/<img[^>]+data-latex=["\']([^"\']+)["\'][^>]+class=["\'][^"\']*math-svg[^"\']*["\'][^>]*>/i', ' $1 ', $html);

        // Bersihkan varian entitas non-breaking space sebelum dan sesudah strip_tags
        $text = str_ireplace(['&nbsp;', '&nbsp', '&#160;', '&#xa0;', '&amp;nbsp;'], ' ', $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_ireplace(['&nbsp;', '&nbsp'], ' ', $text);

        // Normalisasi whitespace Unicode (\u00A0 non-breaking space, \u200B zero-width space, tab, enter, spasi berlebih)
        $text = preg_replace('/[\x{00A0}\x{200B}\s]+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Cuplikan teks ringkas untuk preview / kartu daftar soal.
     */
    public static function snippet(?string $html, int $limit = 80): string
    {
        $plain = self::toPlainText($html);

        if ($plain === '' && !empty($html) && preg_match('/<img\b/i', (string) $html)) {
            return '[Gambar]';
        }

        return \Illuminate\Support\Str::limit($plain, $limit);
    }

    /** Ambil ID video YouTube dari berbagai bentuk URL. */
    public static function youtubeId(string $url): ?string
    {
        $url = trim($url);
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#^[A-Za-z0-9_-]{11}$#', $url)) {
            return $url;
        }
        return null;
    }
}
