<?php

namespace Tests\Unit;

use App\Support\Geofence;
use PHPUnit\Framework\TestCase;

class GeofenceTest extends TestCase
{
    public function test_haversine_zero_for_same_point(): void
    {
        $this->assertEqualsWithDelta(0, Geofence::distanceMeters(-6.2, 106.8, -6.2, 106.8), 0.01);
    }

    public function test_accuracy_acceptable_requires_value_and_rejects_coarse(): void
    {
        $this->assertFalse(Geofence::accuracyAcceptable(null));
        $this->assertTrue(Geofence::accuracyAcceptable(40));
        $this->assertTrue(Geofence::accuracyAcceptable(150));
        $this->assertFalse(Geofence::accuracyAcceptable(151));
        $this->assertFalse(Geofence::accuracyAcceptable(-1));
    }

    public function test_within_radius_uses_fixed_server_tolerance_not_client_accuracy(): void
    {
        $tol = Geofence::SOFT_TOLERANCE_M; // 50

        $this->assertTrue(Geofence::withinRadius(100, 100));
        $this->assertTrue(Geofence::withinRadius(100 + $tol, 100));
        $this->assertFalse(Geofence::withinRadius(100 + $tol + 1, 100));

        // Klaim accuracy klien tidak melebarkan radius (API tidak menerima accuracy di withinRadius).
        $this->assertSame(150.0, Geofence::effectiveRadius(100));
    }

    public function test_effective_radius_is_radius_plus_soft_tolerance(): void
    {
        $this->assertSame(
            100.0 + Geofence::SOFT_TOLERANCE_M,
            Geofence::effectiveRadius(100)
        );
        $this->assertSame(
            200.0 + Geofence::SOFT_TOLERANCE_M,
            Geofence::effectiveRadius(200)
        );
        $this->assertSame(
            100.0 + Geofence::SOFT_TOLERANCE_M + 100.0,
            Geofence::effectiveRadius(100, 100)
        );
    }

    public function test_within_radius_honors_rush_bonus(): void
    {
        // 200 m jarak, radius 100 → soft 50 = 150 (tolak); +bonus 100 = 250 (terima)
        $this->assertFalse(Geofence::withinRadius(200, 100));
        $this->assertTrue(Geofence::withinRadius(200, 100, 100));
    }

    public function test_sanitize_point_label_strips_html(): void
    {
        $this->assertSame('Titik', Geofence::sanitizePointLabel('<img src=x onerror=alert(1)>'));
        $this->assertSame('Gerbang', Geofence::sanitizePointLabel('<script>x</script>Gerbang'));
        $this->assertSame('Gerbang Utama', Geofence::sanitizePointLabel('  Gerbang   Utama  '));
        $this->assertSame('Titik', Geofence::sanitizePointLabel(''));
    }

    public function test_normalize_rush_hm_rejects_invalid(): void
    {
        $this->assertSame('06:30', Geofence::normalizeRushHm('06:30'));
        $this->assertNull(Geofence::normalizeRushHm('99:99'));
        $this->assertNull(Geofence::normalizeRushHm('25:00'));
        $this->assertNull(Geofence::normalizeRushHm('06:30:00')); // sudah harus dinormalisasi di controller
    }
}
