<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Support\Facades\Storage;

/**
 * Deteksi kemiripan / wajah ganda berbasis embedding wajah.
 * Tiap orang diringkas menjadi 1 vektor centroid (rata-rata sampel, dinormalisasi),
 * lalu kemiripan = cosine similarity (dot product karena sudah ternormalisasi).
 */
class FaceMatch
{
    /** Ambang default "kemungkinan wajah sama" (cosine). Bisa disetel via ?min=. */
    public const THRESHOLD = 0.92;

    /** Rata-rata sampel → 1 vektor ternormalisasi. */
    public static function centroid(?array $samples): ?array
    {
        $samples = array_values(array_filter((array) $samples, fn($s) => is_array($s) && count($s) >= 64));
        if (empty($samples)) return null;

        $dim = count($samples[0]);
        $sum = array_fill(0, $dim, 0.0);
        foreach ($samples as $s) {
            for ($i = 0; $i < $dim; $i++) $sum[$i] += (float) ($s[$i] ?? 0);
        }
        $n = count($samples);
        $norm = 0.0;
        for ($i = 0; $i < $dim; $i++) { $sum[$i] /= $n; $norm += $sum[$i] * $sum[$i]; }
        $norm = sqrt($norm);
        if ($norm > 0) for ($i = 0; $i < $dim; $i++) $sum[$i] /= $norm;
        return $sum;
    }

    /** URL foto wajah — dukung path storage (baru) & data-URL base64 (lama). URL relatif agar portabel. */
    public static function photoUrl(?string $v): ?string
    {
        if (empty($v)) return null;
        if (str_starts_with($v, 'data:') || str_starts_with($v, 'http')) return $v; // legacy / absolut
        return '/storage/' . ltrim($v, '/');
    }

    /** Simpan foto (data-URL) ke storage Laravel, kembalikan PATH. Pertahankan lama jika tak ada foto baru. */
    public static function saveFromDataUrl(?string $dataUrl, string $ownerUuid, ?string $oldPath = null): ?string
    {
        if (empty($dataUrl)) return $oldPath;                 // tak kirim foto → pertahankan
        if (!str_starts_with($dataUrl, 'data:')) return $dataUrl; // sudah berupa path
        $comma = strpos($dataUrl, ',');
        if ($comma === false) return $oldPath;
        $bin = base64_decode(substr($dataUrl, $comma + 1));
        if ($bin === false) return $oldPath;

        $path = 'faces/' . $ownerUuid . '_' . now()->format('YmdHis') . '.jpg';
        Storage::disk('public')->put($path, $bin);

        // hapus file lama (kalau path file, bukan base64 lama)
        if ($oldPath && !str_starts_with($oldPath, 'data:')) {
            Storage::disk('public')->delete($oldPath);
        }
        return $path;
    }

    /** Cosine similarity dua vektor ternormalisasi (= dot product). */
    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) $dot += $a[$i] * $b[$i];
        return $dot;
    }

    /** Semua orang terdaftar wajah: [{uuid,nama,tipe,centroid,foto?}]. */
    public static function allRegistered(?string $excludeUuid = null, bool $withPhoto = false): array
    {
        $cols = ['uuid', 'nama', 'face_descriptor'];
        if ($withPhoto) $cols[] = 'face_photo';

        $out = [];
        foreach (Siswa::whereNotNull('face_descriptor')->get($cols) as $s) {
            if ($s->uuid === $excludeUuid) continue;
            $c = self::centroid($s->face_descriptor);
            if ($c) $out[] = ['uuid' => $s->uuid, 'nama' => $s->nama, 'tipe' => 'siswa', 'centroid' => $c, 'foto' => $withPhoto ? self::photoUrl($s->face_photo) : null];
        }
        foreach (Guru::whereNotNull('face_descriptor')->get($cols) as $g) {
            if ($g->uuid === $excludeUuid) continue;
            $c = self::centroid($g->face_descriptor);
            if ($c) $out[] = ['uuid' => $g->uuid, 'nama' => $g->nama, 'tipe' => 'guru', 'centroid' => $c, 'foto' => $withPhoto ? self::photoUrl($g->face_photo) : null];
        }
        return $out;
    }

    /** Cari kemiripan tertinggi sebuah wajah baru dengan orang lain yang sudah terdaftar. */
    public static function bestMatch(?array $newDescriptors, ?string $excludeUuid): ?array
    {
        $c = self::centroid($newDescriptors);
        if (!$c) return null;

        $best = null;
        foreach (self::allRegistered($excludeUuid) as $p) {
            $sim = self::cosine($c, $p['centroid']);
            if (!$best || $sim > $best['similarity']) {
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
        usort($pairs, fn($x, $y) => $y['similarity'] <=> $x['similarity']);
        return array_slice($pairs, 0, $limit);
    }
}
