<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Geofence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GeofenceEvaluateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setPrimaryPin(): void
    {
        Setting::set('sekolah_lat', '-6.200000');
        Setting::set('sekolah_lng', '106.816666');
        Setting::set('absen_radius', '100');
        Setting::set('sekolah_geo_points', '[]');
        Setting::set('absen_rush_bonus', '0');
    }

    public function test_school_points_includes_utama_and_extras(): void
    {
        $this->setPrimaryPin();
        Setting::set('sekolah_geo_points', json_encode([
            ['label' => '<b>Lapangan</b>', 'lat' => -6.198, 'lng' => 106.816666, 'radius' => 120],
            ['label' => 'XSS <img src=x onerror=1>', 'lat' => -6.197, 'lng' => 106.816666],
        ]));

        $points = Geofence::schoolPoints();
        $this->assertCount(3, $points);
        $this->assertSame('Utama', $points[0]['label']);
        $this->assertSame('Lapangan', $points[1]['label']); // HTML stripped
        $this->assertSame('XSS', $points[2]['label']);
        $this->assertSame(120.0, $points[1]['radius']);
        $this->assertSame(100.0, $points[2]['radius']); // ikut default
    }

    public function test_evaluate_prefers_extra_point_when_closer(): void
    {
        $this->setPrimaryPin();
        Setting::set('sekolah_geo_points', json_encode([
            ['label' => 'Lapangan', 'lat' => -6.198000, 'lng' => 106.816666, 'radius' => 120],
        ]));

        $eval = Geofence::evaluate(-6.198000, 106.816666);
        $this->assertNotNull($eval);
        $this->assertTrue($eval['ok']);
        $this->assertSame('Lapangan', $eval['label']);
        $this->assertLessThan(5, $eval['dist']);
    }

    public function test_evaluate_returns_null_without_points(): void
    {
        Setting::set('sekolah_lat', '');
        Setting::set('sekolah_lng', '');
        Setting::set('sekolah_geo_points', '[]');

        $this->assertNull(Geofence::evaluate(-6.2, 106.8));
    }

    public function test_rush_bonus_only_inside_window(): void
    {
        Setting::set('absen_rush_bonus', '100');
        Setting::set('absen_rush_start', '06:30');
        Setting::set('absen_rush_end', '07:45');

        Carbon::setTestNow(Carbon::parse('2026-07-13 07:20:00', config('app.timezone')));
        $this->assertSame(100.0, Geofence::rushBonusMeters());

        Carbon::setTestNow(Carbon::parse('2026-07-13 10:00:00', config('app.timezone')));
        $this->assertSame(0.0, Geofence::rushBonusMeters());
    }

    public function test_rush_bonus_zero_when_invalid_stored_times(): void
    {
        Setting::set('absen_rush_bonus', '100');
        Setting::set('absen_rush_start', '99:99');
        Setting::set('absen_rush_end', '07:45');

        Carbon::setTestNow(Carbon::parse('2026-07-13 07:20:00', config('app.timezone')));
        $this->assertSame(0.0, Geofence::rushBonusMeters());
    }

    public function test_evaluate_applies_rush_bonus_to_effective_radius(): void
    {
        $this->setPrimaryPin();
        Setting::set('absen_rush_bonus', '100');
        Setting::set('absen_rush_start', '06:30');
        Setting::set('absen_rush_end', '07:45');

        // ~167 m dari pin — tanpa bonus ditolak (100+50=150), dengan bonus lolos (250)
        Carbon::setTestNow(Carbon::parse('2026-07-13 07:20:00', config('app.timezone')));
        $inRush = Geofence::evaluate(-6.198500, 106.816666);
        $this->assertTrue($inRush['ok']);
        $this->assertSame(100.0, $inRush['bonus']);

        Carbon::setTestNow(Carbon::parse('2026-07-13 10:00:00', config('app.timezone')));
        $outRush = Geofence::evaluate(-6.198500, 106.816666);
        $this->assertFalse($outRush['ok']);
        $this->assertSame(0.0, $outRush['bonus']);
    }

    public function test_extra_point_radius_capped_at_1000(): void
    {
        $this->setPrimaryPin();
        Setting::set('sekolah_geo_points', json_encode([
            ['label' => 'Luas', 'lat' => -6.198, 'lng' => 106.816666, 'radius' => 5000],
        ]));

        $points = Geofence::schoolPoints();
        $this->assertSame(1000.0, $points[1]['radius']);
    }
}
