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

        // Buang komentar HTML / Word XML comments / namespace tags (misal <o:p>, <w:wordDocument>)
        $html = preg_replace('#<!--[\s\S]*?-->#', '', $html);
        $html = preg_replace('#</?[a-z0-9]+:[a-z0-9]+[^>]*>#i', '', $html);

        // Buang tag <font> bawaan Word/web lama (pertahankan isinya agar tidak merusak teks)
        $html = preg_replace('#</?font\b[^>]*>#i', '', $html);

        // Buang span tab Word (mso-tab-count) dan ganti spasi
        $html = preg_replace('#<span[^>]*mso-tab-count[^>]*>.*?</span>#is', ' ', $html);

        // Bersihkan style inline yang merusak font aplikasi & tata letak (font-family, font-size, line-height, letter-spacing, text-indent, tab-stops, mso-*, margin*, padding*)
        $html = preg_replace_callback('#\sstyle=(["\'])(.*?)\1#is', function ($m) {
            $style = $m[2];
            $style = preg_replace('#(?:font-family|font-size|line-height|letter-spacing|text-indent|tab-stops?|mso-[a-z0-9\-]+|(?:margin|padding)(?:-(?:left|right|top|bottom))?)\s*:\s*[^;]+;?#i', '', $style);
            $style = trim(trim($style), ';');
            return $style !== '' ? ' style="' . htmlspecialchars($style, ENT_QUOTES) . '"' : '';
        }, $html);

        // Buang class bawaan Word/Office (MsoNormal, MsoList, apple-converted-space, dsb)
        $html = preg_replace_callback('#\sclass=(["\'])(.*?)\1#is', function ($m) {
            $classes = array_filter(explode(' ', $m[2]), fn($c) => !preg_match('#^(Mso|apple-)#i', $c) && trim($c) !== '');
            return !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        }, $html);

        // Ganti tab literal (\t) dengan satu spasi agar layout tidak terdorong jauh ke kanan
        $html = str_replace("\t", ' ', $html);

        // Hapus span kosong atau span yang hanya berisi spasi / &nbsp; / \u00a0
        while (preg_match('~<span[^>]*>(?:\s|&nbsp;|&#160;|&#xa0;|\xC2\xA0)*</span>~iu', $html)) {
            $html = preg_replace('~<span[^>]*>(?:\s|&nbsp;|&#160;|&#xa0;|\xC2\xA0)*</span>~iu', '', $html);
        }

        // Unwrap <span> yang sudah polos tanpa atribut
        while (preg_match('#<span\s*>(.*?)</span>#is', $html)) {
            $html = preg_replace('#<span\s*>(.*?)</span>#is', '$1', $html);
        }

        // Ganti run 2 atau lebih non-breaking space / whitespace Unicode dengan satu spasi normal
        $html = preg_replace('~(?:&nbsp;|&#160;|&#xa0;|\xC2\xA0){2,}~i', ' ', $html);

        // Bersihkan spasi/&nbsp; di awal dan akhir teks keseluruhan
        $html = preg_replace('~^(?:\s|&nbsp;|&#160;|&#xa0;|\xC2\xA0)+~iu', '', $html);
        $html = preg_replace('~(?:\s|&nbsp;|&#160;|&#xa0;|\xC2\xA0)+$~iu', '', $html);

        // Bersihkan spasi/tab yang menggantung di awal baris setelah <br>
        $html = preg_replace('~(<br\s*/?>)\s*(?:&nbsp;|&#160;|&#xa0;|\xC2\xA0|\s)+~iu', '$1', $html);

        // Bersihkan spasi/tab yang menggantung di awal blok (<p>, <div>, <li>) jika ada konten setelahnya
        $html = preg_replace('~(<(?:p|div|li)\b[^>]*>)\s*(?:&nbsp;|&#160;|&#xa0;|\xC2\xA0|\s)+(?=[^<\s]|<(?!/))~iu', '$1', $html);

        // Bersihkan spasi/tab yang menggantung sebelum tag penutup blok (</p>, </div>, </li>)
        $html = preg_replace('~(?:&nbsp;|&#160;|&#xa0;|\xC2\xA0|\s)+\s*(</(?:p|div|li)>)~iu', '$1', $html);

        return trim($html);
    }

    /**
     * Pembersih HTML khusus untuk Kop Surat Resmi Sekolah.
     * Membersihkan vektor XSS dan metadata kotor Word/Office,
     * tetapi MEMPERTAHANKAN font-size, font-family, font-weight, text-align, dan color
     * yang telah diatur oleh admin sekolah di menu Pengaturan.
     */
    public static function cleanKop(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        // Buang tag berbahaya beserta isinya.
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta|base)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta|base)\b[^>]*/?>#is', '', $html);

        // Atribut event handler: on...="..."
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);

        // URI berbahaya di href/src.
        $html = preg_replace('#\s(href|src)\s*=\s*("|\')\s*(javascript|vbscript|data\s*:\s*text/html)[^"\']*\2#i', ' $1=$2#$2', $html);

        // Buang komentar HTML / Word XML comments / namespace tags (misal <o:p>, <w:wordDocument>)
        $html = preg_replace('#<!--[\s\S]*?-->#', '', $html);
        $html = preg_replace('#</?[a-z0-9]+:[a-z0-9]+[^>]*>#i', '', $html);

        // Buang span tab Word (mso-tab-count) dan ganti spasi
        $html = preg_replace('#<span[^>]*mso-tab-count[^>]*>.*?</span>#is', ' ', $html);

        // Bersihkan hanya style kotor Office (mso-*, tab-stops) tanpa membuang font-size, font-family, color, font-weight, text-align
        $html = preg_replace_callback('#\sstyle=(["\'])(.*?)\1#is', function ($m) {
            $style = $m[2];
            // Hapus mso-* dan tab-stops
            $style = preg_replace('#(?:tab-stops?|mso-[a-z0-9\-]+)\s*:\s*[^;]+;?#i', '', $style);
            // Normalisasi spasi dan titik koma
            $style = trim(trim($style), ';');
            $style = str_replace('"', '&quot;', $style);
            return $style !== '' ? ' style="' . $style . '"' : '';
        }, $html);

        // Buang class bawaan Word/Office (MsoNormal, MsoList, apple-converted-space, dsb)
        $html = preg_replace_callback('#\sclass=(["\'])(.*?)\1#is', function ($m) {
            $classes = array_filter(explode(' ', $m[2]), fn($c) => !preg_match('#^(Mso|apple-)#i', $c) && trim($c) !== '');
            return !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
        }, $html);

        // Ganti tab literal (\t) dengan spasi
        $html = str_replace("\t", ' ', $html);

        // Ganti run 2 atau lebih non-breaking space / whitespace Unicode dengan satu spasi normal
        $html = preg_replace('~(?:&nbsp;|&#160;|&#xa0;|\xC2\xA0){2,}~i', ' ', $html);

        // Hapus span kosong tanpa atribut
        while (preg_match('#<span\s*>(.*?)</span>#is', $html)) {
            $html = preg_replace('#<span\s*>(.*?)</span>#is', '$1', $html);
        }
        $html = preg_replace('#<span[^>]*>\s*</span>#is', '', $html);

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
