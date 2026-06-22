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
