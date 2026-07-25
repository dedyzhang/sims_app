<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Support\Geofence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Toggle admin: pelacakan GPS absen QR bisa dinonaktifkan/diaktifkan per preferensi sekolah
 * (Setting qr_geo_wajib, App\Support\Geofence::wajib()). Default TETAP aktif (perilaku lama,
 * tidak berubah) — hanya jadi opsional saat admin secara eksplisit mematikannya.
 */
class QrGeoToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'geo_toggle_admin', 'password' => Hash::make('x'), 'access' => 'superadmin']);
    }

    private function siswaUser(): array
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $user = User::create(['username' => 'geo_toggle_siswa', 'password' => Hash::make('x'), 'access' => 'siswa']);
        // face_descriptor diisi supaya lolos EnsureFaceRegistered (bukan fokus tes ini).
        $siswa = Siswa::create([
            'id_login' => $user->uuid, 'nama' => 'Siswa Geo Toggle', 'nis' => '999101', 'id_kelas' => $kelas->uuid, 'jk' => 'L',
            'face_descriptor' => [array_fill(0, 64, 0.1)],
        ]);

        return [$user, $siswa];
    }

    private function setupQr(): string
    {
        Setting::set('kaih_wajib_sebelum_absen', '0');
        Setting::set('cara_absensi_guru', 'barcode');
        Setting::set('qr_absensi_aktif', '1');
        Setting::set('sekolah_lat', '-6.200000');
        Setting::set('sekolah_lng', '106.816666');
        Setting::set('absen_radius', '100');
        Setting::set('sekolah_geo_points', '[]');
        Setting::set('absen_rush_bonus', '0');

        return substr(hash_hmac('sha256', 'qrabsen|'.now()->toDateString(), (string) config('app.key')), 0, 12);
    }

    public function test_default_geo_wajib_aktif(): void
    {
        $this->assertTrue(Geofence::wajib());
    }

    public function test_default_absen_qr_jauh_dari_sekolah_ditolak(): void
    {
        $token = $this->setupQr();
        [$user] = $this->siswaUser();

        // ~11 km dari titik sekolah — jauh di luar radius 100 m.
        $this->actingAs($user)->postJson('/absen-qr', [
            'token' => $token, 'lat' => '-6.300000', 'lng' => '106.900000', 'accuracy' => 20,
        ])->assertStatus(422);
    }

    public function test_absen_qr_tanpa_lokasi_ditolak_saat_geo_masih_wajib(): void
    {
        $token = $this->setupQr();
        [$user] = $this->siswaUser();

        $this->actingAs($user)->postJson('/absen-qr', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng', 'accuracy']);
    }

    public function test_geo_dinonaktifkan_absen_qr_tanpa_lokasi_berhasil_dari_mana_saja(): void
    {
        $token = $this->setupQr();
        Setting::set('qr_geo_wajib', '0');
        [$user, $siswa] = $this->siswaUser();

        $this->assertFalse(Geofence::wajib());

        // Tak kirim lat/lng/accuracy sama sekali — mensimulasikan klien yg tak lagi meminta GPS.
        $res = $this->actingAs($user)->postJson('/absen-qr', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $row = Absensi::where('id_siswa', $siswa->uuid)->whereDate('tanggal', now())->first();
        $this->assertNotNull($row);
        $this->assertSame('hadir', $row->status);

        // Audit lokasi disimpan null (bukan 0,0 yg terbaca spt koordinat sungguhan) krn memang
        // tak ada data GPS yg dikirim.
        $this->assertNull($row->geo_lat);
        $this->assertNull($row->geo_lng);
    }

    public function test_geo_dinonaktifkan_tapi_klien_tetap_kirim_lokasi_tetap_diterima(): void
    {
        $token = $this->setupQr();
        Setting::set('qr_geo_wajib', '0');
        [$user] = $this->siswaUser();

        // Lokasi jauh dari sekolah tetap TIDAK ditolak krn geo tak lagi jadi gate — hanya audit.
        $this->actingAs($user)->postJson('/absen-qr', [
            'token' => $token, 'lat' => '-6.300000', 'lng' => '106.900000', 'accuracy' => 20,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_geo_config_endpoint_melaporkan_status_toggle(): void
    {
        $this->setupQr();
        [$user] = $this->siswaUser();

        $this->actingAs($user)->getJson(route('absen.qr.geoConfig'))
            ->assertOk()
            ->assertJsonPath('geo_wajib', true);

        Setting::set('qr_geo_wajib', '0');
        $this->actingAs($user)->getJson(route('absen.qr.geoConfig'))
            ->assertOk()
            ->assertJsonPath('geo_wajib', false);
    }

    public function test_halaman_absen_qr_render_baik_geo_aktif_maupun_nonaktif(): void
    {
        $this->setupQr();
        [$user] = $this->siswaUser();

        $this->actingAs($user)->get('/absen-qr')->assertOk()->assertSee('Absen QR');

        Setting::set('qr_geo_wajib', '0');
        $this->actingAs($user)->get('/absen-qr')->assertOk()->assertSee('Absen QR');
    }

    public function test_halaman_izin_pulang_guru_render_baik_geo_aktif_maupun_nonaktif(): void
    {
        Setting::set('cara_absensi_guru', 'barcode');
        Setting::set('sekolah_lat', '-6.200000');
        Setting::set('sekolah_lng', '106.816666');
        Setting::set('absen_radius', '100');

        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $guruUser = User::create(['username' => 'geo_toggle_guru', 'password' => Hash::make('x'), 'access' => 'guru']);
        \App\Models\Guru::create([
            'id_login' => $guruUser->uuid, 'nama' => 'Guru Geo Toggle', 'nik' => '888101', 'jk' => 'L',
            'face_descriptor' => [array_fill(0, 64, 0.1)],
        ]);

        $this->actingAs($guruUser)->get('/presensi-guru/saya')->assertOk();

        Setting::set('qr_geo_wajib', '0');
        $this->actingAs($guruUser)->get('/presensi-guru/saya')->assertOk();
    }

    public function test_admin_bisa_menyimpan_toggle_geo_wajib(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('setting.lokasiQr'), [
            'sekolah_lat' => '-6.2', 'sekolah_lng' => '106.8', 'absen_radius' => 200,
            'sekolah_geo_points' => '[]',
            // qr_geo_wajib sengaja TIDAK dikirim (checkbox tak dicentang) → harus tersimpan '0'.
        ])->assertRedirect();

        $this->assertSame('0', Setting::get('qr_geo_wajib'));
        $this->assertFalse(Geofence::wajib());

        $this->actingAs($admin)->post(route('setting.lokasiQr'), [
            'sekolah_lat' => '-6.2', 'sekolah_lng' => '106.8', 'absen_radius' => 200,
            'sekolah_geo_points' => '[]', 'qr_geo_wajib' => '1',
        ])->assertRedirect();

        $this->assertSame('1', Setting::get('qr_geo_wajib'));
        $this->assertTrue(Geofence::wajib());
    }
}
