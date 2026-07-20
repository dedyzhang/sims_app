<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Geofence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingLokasiQrTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::create([
            'username' => 'lokasi_qr_admin',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);
    }

    private function siswaUser(): User
    {
        return User::create([
            'username' => 'lokasi_qr_siswa',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
    }

    public function test_admin_can_save_multi_point_and_rush_settings(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('setting.lokasiQr'), [
            'sekolah_lat' => '-6.200000',
            'sekolah_lng' => '106.816666',
            'absen_radius' => 200,
            'qr_absensi_mode' => 'harian',
            'qr_absensi_aktif' => '1',
            'absen_rush_bonus' => 80,
            'absen_rush_start' => '06:30:00', // seconds dinormalisasi
            'absen_rush_end' => '07:45',
            'sekolah_geo_points' => json_encode([
                ['label' => '<script>x</script>Gerbang', 'lat' => -6.201, 'lng' => 106.817, 'radius' => 5000],
                ['label' => 'Bad', 'lat' => 999, 'lng' => 106.8], // invalid — diabaikan
            ]),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame('-6.200000', Setting::get('sekolah_lat'));
        $this->assertSame('200', (string) Setting::get('absen_radius'));
        $this->assertSame('80', Setting::get('absen_rush_bonus'));
        $this->assertSame('06:30', Setting::get('absen_rush_start'));
        $this->assertSame('07:45', Setting::get('absen_rush_end'));

        $points = json_decode(Setting::get('sekolah_geo_points'), true);
        $this->assertCount(1, $points);
        $this->assertSame('Gerbang', $points[0]['label']);
        $this->assertSame(1000, $points[0]['radius']); // capped
    }

    public function test_invalid_rush_time_rejected(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->from(route('setting.index'))->post(route('setting.lokasiQr'), [
            'sekolah_lat' => '-6.2',
            'sekolah_lng' => '106.8',
            'absen_radius' => 200,
            'absen_rush_bonus' => 100,
            'absen_rush_start' => '00:00',
            'absen_rush_end' => '99:99',
            'sekolah_geo_points' => '[]',
        ]);

        $response->assertRedirect(route('setting.index'));
        $response->assertSessionHasErrors('absen_rush_start');
    }

    public function test_non_admin_cannot_save_lokasi_qr(): void
    {
        $siswa = $this->siswaUser();

        $this->actingAs($siswa)->post(route('setting.lokasiQr'), [
            'sekolah_lat' => '-6.2',
            'sekolah_lng' => '106.8',
            'absen_radius' => 200,
            'sekolah_geo_points' => '[]',
        ])->assertForbidden();
    }

    public function test_geo_config_endpoint_returns_live_rush_bonus(): void
    {
        Setting::set('cara_absensi_guru', 'barcode');
        Setting::set('qr_absensi_aktif', '1');
        Setting::set('sekolah_lat', '-6.200000');
        Setting::set('sekolah_lng', '106.816666');
        Setting::set('absen_radius', '100');
        Setting::set('sekolah_geo_points', '[]');
        Setting::set('absen_rush_bonus', '100');
        Setting::set('absen_rush_start', '06:30');
        Setting::set('absen_rush_end', '07:45');

        Carbon::setTestNow(Carbon::parse('2026-07-13 07:20:00', config('app.timezone')));

        $siswa = $this->siswaUser();
        $this->actingAs($siswa)->getJson(route('absen.qr.geoConfig'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('rush_bonus', 100)
            ->assertJsonPath('soft_tolerance', Geofence::SOFT_TOLERANCE_M)
            ->assertJsonPath('points.0.label', 'Utama');

        Carbon::setTestNow(Carbon::parse('2026-07-13 10:00:00', config('app.timezone')));
        $this->actingAs($siswa)->getJson(route('absen.qr.geoConfig'))
            ->assertOk()
            ->assertJsonPath('rush_bonus', 0);
    }
}
