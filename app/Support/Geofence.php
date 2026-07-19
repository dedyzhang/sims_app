<?php

namespace App\Support;

/**
 * Validasi geofence absensi QR — Haversine + toleransi GPS tetap (server-side).
 *
 * Catatan keamanan: lat/lng/accuracy dari klien tidak boleh melebarkan radius.
 * Soft tolerance adalah konstanta server; accuracy hanya untuk gate kualitas GPS + audit.
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

    /** Soft geofence: jarak ≤ radius + toleransi server tetap. */
    public static function withinRadius(float $dist, float $radius): bool
    {
        return $dist <= self::effectiveRadius($radius);
    }

    /** Radius efektif (radius admin + toleransi GPS server). */
    public static function effectiveRadius(float $radius): float
    {
        return $radius + (float) self::SOFT_TOLERANCE_M;
    }
}
