<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class FaceMatch
{
    /** Ambang default "kemungkinan wajah sama" (cosine). Bisa disetel via ?min=. */
    public const THRESHOLD = 0.92;

    /** Kompresi foto wajah: lebar maksimum & kualitas WebP (lebih tinggi dari kompresi materi biasa — dipakai utk verifikasi visual). */
    private const PHOTO_MAX_WIDTH = 480;
    private const PHOTO_QUALITY = 88;

    /** Rata-rata sampel → 1 vektor ternormalisasi. */
    public static function centroid(?array $samples): ?array
    {
        $samples = array_values(array_filter((array) $samples, fn ($s) => is_array($s) && count($s) >= 64));
        if (empty($samples)) {
            return null;
        }

        $dim = count($samples[0]);
        $sum = array_fill(0, $dim, 0.0);
        foreach ($samples as $s) {
            for ($i = 0; $i < $dim; $i++) {
                $sum[$i] += (float) ($s[$i] ?? 0);
            }
        }
        $n = count($samples);
        $norm = 0.0;
        for ($i = 0; $i < $dim; $i++) {
            $sum[$i] /= $n;
            $norm += $sum[$i] * $sum[$i];
        }
        $norm = sqrt($norm);
        if ($norm > 0) {
            for ($i = 0; $i < $dim; $i++) {
                $sum[$i] /= $norm;
            }
        }

        return $sum;
    }

    /** URL foto wajah — hanya path storage faces/{uuid}_*.jpg milik pemilik. */
    public static function photoUrl(?string $v, ?string $ownerUuid = null): ?string
    {
        if (empty($v)) {
            return null;
        }
        if (str_starts_with($v, 'data:')) {
            return $v;
        }
        if (str_starts_with($v, 'http')) {
            return null;
        }
        // Terima jpg/jpeg/png/webp: foto wajah dikompres ke WebP (fallback JPEG bila driver tak
        // mendukung), jadi ekstensinya bisa bermacam — jangan batasi hanya .jpg (bikin URL null).
        if (! preg_match('/^faces\/([a-f0-9\-]+)_\d{14}\.(?:jpe?g|png|webp)$/', $v, $m)) {
            return null;
        }
        if ($ownerUuid !== null && $m[1] !== $ownerUuid) {
            return null;
        }

        return '/storage/'.$v;
    }

    /** Simpan foto (data-URL) ke storage Laravel, kembalikan PATH. */
    public static function saveFromDataUrl(?string $dataUrl, string $ownerUuid, ?string $oldPath = null): ?string
    {
        if (empty($dataUrl)) {
            return self::isValidStoredPath($oldPath, $ownerUuid) ? $oldPath : null;
        }
        if (! str_starts_with($dataUrl, 'data:image/')) {
            return self::isValidStoredPath($oldPath, $ownerUuid) ? $oldPath : null;
        }
        $comma = strpos($dataUrl, ',');
        if ($comma === false) {
            return self::isValidStoredPath($oldPath, $ownerUuid) ? $oldPath : null;
        }
        $bin = base64_decode(substr($dataUrl, $comma + 1));
        if ($bin === false || @getimagesizefromstring($bin) === false) {
            return self::isValidStoredPath($oldPath, $ownerUuid) ? $oldPath : null;
        }

        // Kompres ulang di server (bukan simpan mentah dari kanvas browser): resize bila perlu
        // + re-encode WebP kualitas tinggi. Beda dari FileCompressionService (materi/dokumen) —
        // foto wajah dipakai utk verifikasi visual jadi kualitasnya sengaja dijaga lebih tinggi.
        $ext = 'jpg';
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($bin);
            if ($image->width() > self::PHOTO_MAX_WIDTH) {
                $image->scaleDown(width: self::PHOTO_MAX_WIDTH);
            }
            $bin = (string) $image->toWebp(self::PHOTO_QUALITY);
            $ext = 'webp';
        } catch (\Throwable $e) {
            // gagal re-encode (mis. driver tak tersedia) → simpan data asli apa adanya
        }

        $path = 'faces/' . $ownerUuid . '_' . now()->format('YmdHis') . '.' . $ext;
        Storage::disk('public')->put($path, $bin);

        if ($oldPath && self::isValidStoredPath($oldPath, $ownerUuid)) {
            Storage::disk('public')->delete($oldPath);
        }

        return $path;
    }

    private static function isValidStoredPath(?string $path, string $ownerUuid): bool
    {
        if (empty($path) || str_starts_with($path, 'data:')) {
            return false;
        }

        return (bool) preg_match('/^faces\/'.preg_quote($ownerUuid, '/').'_\d{14}\.(?:jpe?g|png|webp)$/', $path);
    }

    /** Cosine similarity dua vektor ternormalisasi (= dot product). */
    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
        }

        return $dot;
    }

    /** Semua orang terdaftar wajah: [{uuid,nama,tipe,centroid,foto?}]. */
    public static function allRegistered(?string $excludeUuid = null, bool $withPhoto = false): array
    {
        $cols = ['uuid', 'nama', 'face_descriptor'];
        if ($withPhoto) {
            $cols[] = 'face_photo';
        }

        $out = [];
        foreach (Siswa::whereNotNull('face_descriptor')->get($cols) as $s) {
            if ($s->uuid === $excludeUuid) {
                continue;
            }
            $c = self::centroid($s->face_descriptor);
            if ($c) {
                $out[] = ['uuid' => $s->uuid, 'nama' => $s->nama, 'tipe' => 'siswa', 'centroid' => $c, 'foto' => $withPhoto ? self::photoUrl($s->face_photo, $s->uuid) : null];
            }
        }
        foreach (Guru::whereNotNull('face_descriptor')->get($cols) as $g) {
            if ($g->uuid === $excludeUuid) {
                continue;
            }
            $c = self::centroid($g->face_descriptor);
            if ($c) {
                $out[] = ['uuid' => $g->uuid, 'nama' => $g->nama, 'tipe' => 'guru', 'centroid' => $c, 'foto' => $withPhoto ? self::photoUrl($g->face_photo, $g->uuid) : null];
            }
        }

        return $out;
    }

    /** Cari kemiripan tertinggi sebuah wajah baru dengan orang lain yang sudah terdaftar. */
    public static function bestMatch(?array $newDescriptors, ?string $excludeUuid): ?array
    {
        $c = self::centroid($newDescriptors);
        if (! $c) {
            return null;
        }

        $best = null;
        foreach (self::allRegistered($excludeUuid) as $p) {
            $sim = self::cosine($c, $p['centroid']);
            if (! $best || $sim > $best['similarity']) {
                $best = ['uuid' => $p['uuid'], 'nama' => $p['nama'], 'tipe' => $p['tipe'], 'similarity' => $sim];
            }
        }

        return $best;
    }

    /** Semua pasangan wajah mirip (>= $min), urut menurun. */
    public static function duplicatePairs(float $min, int $limit = 60): array
    {
        $people = self::allRegistered(null, true);
        $n = count($people);
        $pairs = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $sim = self::cosine($people[$i]['centroid'], $people[$j]['centroid']);
                if ($sim >= $min) {
                    $pairs[] = ['a' => $people[$i], 'b' => $people[$j], 'similarity' => $sim];
                }
            }
        }
        usort($pairs, fn ($x, $y) => $y['similarity'] <=> $x['similarity']);

        return array_slice($pairs, 0, $limit);
    }
}
