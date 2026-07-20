<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Validasi geofence absensi QR — Haversine + multi-titik + toleransi GPS tetap (server-side).
 *
 * Catatan keamanan: lat/lng/accuracy dari klien tidak boleh melebarkan radius.
 * Soft tolerance adalah konstanta server; accuracy hanya untuk gate kualitas GPS + audit.
 * Bonus jam sibuk (opsional) juga dikonfigurasi server, bukan dari request klien.
 */
class Geofence
{
    /** Tolak absen bila akurasi GPS lebih buruk dari ini (meter). Wajib dikirim klien. */
    public const MAX_ACCURACY_M = 150;

    /** Toleransi GPS tetap (meter) — tidak diambil dari request klien. */
    public const SOFT_TOLERANCE_M = 50;

    /** Jarak dua koordinat (meter) — Haversine. */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Accuracy wajib ada dan tidak lebih kasar dari MAX_ACCURACY_M. */
    public static function accuracyAcceptable(?float $accuracy): bool
    {
        if ($accuracy === null || !is_finite($accuracy) || $accuracy < 0) {
            return false;
        }

        return $accuracy <= self::MAX_ACCURACY_M;
    }

    /**
     * Soft geofence: jarak ≤ radius + toleransi server (+ bonus jam sibuk opsional).
     */
    public static function withinRadius(float $dist, float $radius, float $bonus = 0): bool
    {
        return $dist <= self::effectiveRadius($radius, $bonus);
    }

    /** Radius efektif (radius admin + toleransi GPS server + bonus jam sibuk). */
    public static function effectiveRadius(float $radius, float $bonus = 0): float
    {
        return $radius + (float) self::SOFT_TOLERANCE_M + max(0.0, $bonus);
    }

    /**
     * Daftar titik sekolah: pin utama (sekolah_lat/lng) + titik tambahan JSON.
     *
     * @return list<array{label: string, lat: float, lng: float, radius: float}>
     */
    public static function schoolPoints(): array
    {
        $defaultRadius = (float) Setting::get('absen_radius', 200);
        $points = [];

        $lat = Setting::get('sekolah_lat');
        $lng = Setting::get('sekolah_lng');
        if ($lat !== null && $lat !== '' && $lng !== null && $lng !== '') {
            $points[] = [
                'label' => 'Utama',
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'radius' => $defaultRadius,
            ];
        }

        $extra = json_decode((string) (Setting::get('sekolah_geo_points', '[]') ?: '[]'), true);
        if (!is_array($extra)) {
            return $points;
        }

        foreach ($extra as $p) {
            if (!is_array($p) || !isset($p['lat'], $p['lng'])) {
                continue;
            }
            if (!is_numeric($p['lat']) || !is_numeric($p['lng'])) {
                continue;
            }
            $r = $defaultRadius;
            if (isset($p['radius']) && $p['radius'] !== '' && $p['radius'] !== null && is_numeric($p['radius'])) {
                $r = (float) $p['radius'];
            }
            $label = self::sanitizePointLabel((string) ($p['label'] ?? 'Titik'));
            $points[] = [
                'label' => $label,
                'lat' => (float) $p['lat'],
                'lng' => (float) $p['lng'],
                'radius' => max(10.0, min(1000.0, $r)),
            ];
            if (count($points) >= 9) { // utama + 8 ekstra
                break;
            }
        }

        return $points;
    }

    /** Label aman untuk toast/popup (cegah XSS lewat innerHTML / Leaflet). */
    public static function sanitizePointLabel(string $label): string
    {
        $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Buang isi <script>/<style> sebelum strip_tags (strip_tags menyisakan teks di dalamnya).
        $label = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $label) ?? '';
        $label = strip_tags($label);
        $label = preg_replace('/[\x00-\x1F\x7F]/u', '', $label) ?? '';
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');
        $label = mb_substr($label, 0, 40);

        return $label !== '' ? $label : 'Titik';
    }

    /**
     * Bonus meter selama jam sibuk pagi (konfigurasi server).
     * Default: +100 m antara 06:30–07:45.
     */
    public static function rushBonusMeters(?DateTimeInterface $now = null): float
    {
        $bonus = (float) Setting::get('absen_rush_bonus', 100);
        if ($bonus <= 0) {
            return 0.0;
        }

        $carbon = $now ? Carbon::parse($now)->timezone(config('app.timezone')) : now();
        $start = self::normalizeRushHm((string) Setting::get('absen_rush_start', '06:30'));
        $end = self::normalizeRushHm((string) Setting::get('absen_rush_end', '07:45'));
        if ($start === null || $end === null) {
            return 0.0;
        }

        $t = $carbon->format('H:i');
        if ($start <= $end) {
            return ($t >= $start && $t <= $end) ? $bonus : 0.0;
        }

        // Rentang melewati tengah malam (jarang, tapi aman).
        return ($t >= $start || $t <= $end) ? $bonus : 0.0;
    }

    /** Terima hanya HH:MM valid 00:00–23:59 (tolak 99:99 dll). */
    public static function normalizeRushHm(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^(\d{2}):(\d{2})$/', $value, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h > 23 || $i > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $i);
    }

    /**
     * Evaluasi lokasi terhadap semua titik — pakai jarak terdekat / yang paling longgar.
     *
     * @return array{
     *     ok: bool,
     *     dist: float,
     *     radius: float,
     *     bonus: float,
     *     effective: float,
     *     label: string,
     *     point: array{label: string, lat: float, lng: float, radius: float}
     * }|null null bila belum ada titik sekolah
     */
    public static function evaluate(float $lat, float $lng, ?DateTimeInterface $now = null): ?array
    {
        $points = self::schoolPoints();
        if ($points === []) {
            return null;
        }

        $bonus = self::rushBonusMeters($now);
        $best = null;

        foreach ($points as $point) {
            $dist = self::distanceMeters($point['lat'], $point['lng'], $lat, $lng);
            $effective = self::effectiveRadius($point['radius'], $bonus);
            $slack = $effective - $dist; // semakin besar semakin "dalam"
            $candidate = [
                'ok' => $dist <= $effective,
                'dist' => $dist,
                'radius' => $point['radius'],
                'bonus' => $bonus,
                'effective' => $effective,
                'label' => $point['label'],
                'point' => $point,
                '_slack' => $slack,
            ];

            if ($best === null
                || ($candidate['ok'] && !$best['ok'])
                || ($candidate['ok'] === $best['ok'] && $candidate['_slack'] > $best['_slack'])
            ) {
                $best = $candidate;
            }
        }

        unset($best['_slack']);

        return $best;
    }
}
