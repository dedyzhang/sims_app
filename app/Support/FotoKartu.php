<?php

namespace App\Support;

/**
 * Kompres+resize foto kartu ID (guru, dst) SEBELUM disimpan. Foto langsung dari kamera ponsel
 * biasa 3-8MB — kalau disematkan mentah (base64) ke HTML yang diparse dompdf utk PULUHAN orang
 * sekaligus (mis. "Cetak Semua" Kartu ID Guru), render PDF jadi sangat lambat / nyaris macet.
 * Area foto di kartu cuma ~54×52.5mm, jadi resolusi tinggi sama sekali tak diperlukan.
 */
class FotoKartu
{
    /** px, sisi terpanjang — cukup tajam @300dpi utk area foto kartu (~54×52.5mm ≈ 638×620px). */
    public const MAX_DIM = 720;
    public const JPEG_QUALITY = 82;

    /**
     * @param  bool  $preserveAlpha  true utk foto PNG cutout transparan (simpan sbg PNG),
     *                                false utk foto biasa/JPEG (simpan sbg JPEG, lebih kecil).
     */
    public static function resize(string $binary, bool $preserveAlpha): string
    {
        $src = @imagecreatefromstring($binary);
        if (! $src) {
            return $binary; // gagal decode — simpan apa adanya drpd gagal total
        }

        // Wajib di-set pada SUMBER juga (bukan cuma kanvas hasil resize) — tanpa ini, PNG yang
        // sudah cukup kecil (tak perlu diresize, $dst===$src) kehilangan channel alpha-nya saat
        // di-imagepng() ulang: alpha ikut "diratakan" jadi hitam solid, bukan tetap transparan.
        if ($preserveAlpha) {
            imagealphablending($src, false);
            imagesavealpha($src, true);
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $longest = max($w, $h);
        $dst = $src;

        if ($longest > self::MAX_DIM) {
            $scale = self::MAX_DIM / $longest;
            $newW = max(1, (int) round($w * $scale));
            $newH = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($newW, $newH);
            if ($preserveAlpha) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        }

        ob_start();
        if ($preserveAlpha) {
            imagepng($dst, null, 6);
        } else {
            imagejpeg($dst, null, self::JPEG_QUALITY);
        }
        $out = ob_get_clean();

        if ($dst !== $src) {
            imagedestroy($dst);
        }
        imagedestroy($src);

        return ($out !== false && $out !== '') ? $out : $binary;
    }
}
