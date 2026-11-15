<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\PresensiGuru;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskAbsensiTanpaLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $kioskToken = 'PxmYMdt7lGliacG1mCtO6lUldYVabCNqBxTTeR2X';
    private Kelas $kelas;
    private Siswa $siswa;
    private Guru $guru;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('kiosk_token', $this->kioskToken);
        Setting::set('cara_absensi_guru', 'wajah');
        Setting::set('scan_kiosk_mode', 'keduanya');
        Setting::set('kaih_wajib_sebelum_absen', '0');
        Setting::set('waktu_terlambat', '07:30');
        Setting::set('waktu_terlambat_guru', '07:30');
        Setting::set('kalender_libur_aktif', '0');

        Semester::create([
            'semester' => 1,
            'tahun' => '2025/2026',
            'aktif' => true,
        ]);

        $this->kelas = Kelas::create([
            'tingkat' => 7,
            'kelas' => 'A',
        ]);

        $this->siswa = Siswa::create([
            'id_kelas' => $this->kelas->uuid,
            'nama' => 'Budi Santoso',
            'nis' => '12345',
            'jk' => 'L',
        ]);

        $this->guru = Guru::create([
            'nama' => 'Guru Pengajar',
            'nip' => '198501012010011001',
            'jk' => 'L',
        ]);
    }

    public function test_kiosk_enter_mengarahkan_ke_scan_dengan_token(): void
    {
        $response = $this->get('/kiosk-absensi/' . $this->kioskToken);

        $response->assertRedirect('/absensi/scan?_kiosk=' . urlencode($this->kioskToken));
    }

    public function test_kiosk_enter_gagal_jika_token_salah(): void
    {
        $response = $this->get('/kiosk-absensi/token-salah-123');

        $response->assertNotFound();
    }

    public function test_akses_scan_kiosk_tanpa_login_berhasil_dan_tidak_crash(): void
    {
        $response = $this->get('/absensi/scan?_kiosk=' . $this->kioskToken);

        $response->assertOk();
        $response->assertViewIs('absensi.scan');
        $response->assertViewHas('isKiosk', true);
        $response->assertViewHas('kioskToken', $this->kioskToken);
    }

    public function test_akses_scan_kiosk_tanpa_token_dialihkan_ke_login(): void
    {
        $response = $this->get('/absensi/scan');

        $response->assertRedirect(route('login'));
    }

    public function test_akses_qr_absensi_kiosk_tanpa_login_berhasil(): void
    {
        $response = $this->get('/qr-absensi?_kiosk=' . $this->kioskToken);

        $response->assertOk();
        $response->assertViewIs('qr.show');
        $response->assertViewHas('isKiosk', true);
    }

    public function test_mark_siswa_pada_kiosk_tanpa_login_berhasil(): void
    {
        $response = $this->postJson(route('absensi.mark'), [
            'id_siswa' => $this->siswa->uuid,
            'id_kelas' => $this->kelas->uuid,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
            '_kiosk' => $this->kioskToken,
            '_via' => 'barcode',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'uuid' => $this->siswa->uuid,
            'nama' => 'Budi Santoso',
        ]);

        $this->assertDatabaseHas('absensis', [
            'id_siswa' => $this->siswa->uuid,
            'status' => 'hadir',
        ]);
    }

    public function test_mark_barcode_siswa_pada_kiosk_tanpa_login_berhasil(): void
    {
        $response = $this->postJson(route('absensi.markBarcode'), [
            'barcode' => '12345',
            'tanggal' => now()->toDateString(),
            '_kiosk' => $this->kioskToken,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'uuid' => $this->siswa->uuid,
        ]);
    }

    public function test_mark_presensi_guru_pada_kiosk_tanpa_login_berhasil(): void
    {
        $response = $this->postJson(route('presensi-guru.mark'), [
            'id_guru' => $this->guru->uuid,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
            'mode' => 'masuk',
            '_kiosk' => $this->kioskToken,
            '_via' => 'barcode',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'mode' => 'masuk',
        ]);

        $this->assertDatabaseHas('presensi_gurus', [
            'id_guru' => $this->guru->uuid,
            'status' => 'hadir',
        ]);
    }
}
