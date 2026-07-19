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
    }
}
