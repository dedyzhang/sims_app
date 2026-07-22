<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mode kamera halaman Scan Absensi (setting scan_kiosk_mode):
 * 'wajah' = kamera hanya mengenali wajah, 'qr' = hanya baca QR kartu pelajar,
 * 'keduanya' (default) = satu kamera membaca wajah dan QR sekaligus.
 */
class ScanKioskModeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Kelas $kelas;
    private Siswa $siswaWajah;
    private Siswa $siswaTanpaWajah;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'wajah']);
        Setting::set('kaih_wajib_sebelum_absen', '0');
        Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);

        $this->admin = User::create([
            'username' => 'admin_scan_mode',
            'password' => Hash::make('x'),
            'access' => 'superadmin',
        ]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->siswaWajah = Siswa::create([
            'id_kelas' => $this->kelas->uuid,
            'nama' => 'Siswa Punya Wajah',
            'nis' => '111111',
            'jk' => 'L',
            'face_descriptor' => [[0.1, 0.2]],
            'face_registered_at' => now(),
        ]);
        $this->siswaTanpaWajah = Siswa::create([
            'id_kelas' => $this->kelas->uuid,
            'nama' => 'Siswa Tanpa Wajah',
            'nis' => '222222',
            'jk' => 'P',
        ]);
    }

    private function payloadFor($response)
    {
        return collect($response->viewData('payload'));
    }

    public function test_setting_scan_kiosk_mode_tersimpan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('setting.scanKioskMode'), ['scan_kiosk_mode' => 'qr'])
            ->assertRedirect();

        $this->assertSame('qr', Setting::get('scan_kiosk_mode'));
    }

    public function test_setting_scan_kiosk_mode_menolak_nilai_asing(): void
    {
        $this->actingAs($this->admin)
            ->post(route('setting.scanKioskMode'), ['scan_kiosk_mode' => 'bebas'])
            ->assertSessionHasErrors('scan_kiosk_mode');
    }

    public function test_default_keduanya_menyertakan_siswa_tanpa_wajah_dan_kedua_engine(): void
    {
        $res = $this->actingAs($this->admin)->get(route('absensi.scan'))->assertOk();

        $res->assertViewHas('scanKioskMode', 'keduanya');
        // Siswa tanpa wajah tetap masuk daftar — bisa absen via QR kartu.
        $payload = $this->payloadFor($res);
        $this->assertTrue($payload->contains(fn ($p) => $p['uuid'] === $this->siswaTanpaWajah->uuid));
        $this->assertTrue($payload->contains(fn ($p) => $p['uuid'] === $this->siswaWajah->uuid));
        // Kedua library dimuat: Human (wajah) + jsQR (fallback QR).
        $res->assertSee('human.js', false)->assertSee('jsQR.js', false);
    }

    public function test_mode_wajah_hanya_siswa_terdaftar_wajah_tanpa_jsqr(): void
    {
        Setting::set('scan_kiosk_mode', 'wajah');

        $res = $this->actingAs($this->admin)->get(route('absensi.scan'))->assertOk();

        $res->assertViewHas('scanKioskMode', 'wajah');
        $payload = $this->payloadFor($res);
        $this->assertFalse($payload->contains(fn ($p) => $p['uuid'] === $this->siswaTanpaWajah->uuid));
        $this->assertTrue($payload->contains(fn ($p) => $p['uuid'] === $this->siswaWajah->uuid));
        $res->assertSee('human.js', false)->assertDontSee('jsQR.js', false);
    }

    public function test_mode_qr_semua_siswa_tanpa_descriptor_dan_tanpa_human(): void
    {
        Setting::set('scan_kiosk_mode', 'qr');

        $res = $this->actingAs($this->admin)->get(route('absensi.scan'))->assertOk();

        $res->assertViewHas('scanKioskMode', 'qr');
        $payload = $this->payloadFor($res);
        // Semua siswa masuk daftar, tapi descriptor wajah TIDAK dikirim (payload ringan).
        $this->assertTrue($payload->contains(fn ($p) => $p['uuid'] === $this->siswaTanpaWajah->uuid));
        $wajah = $payload->first(fn ($p) => $p['uuid'] === $this->siswaWajah->uuid);
        $this->assertSame([], $wajah['desc']);
        $res->assertDontSee('human.js', false)->assertSee('jsQR.js', false);
    }

    public function test_kartu_qr_tetap_bisa_absen_saat_metode_sekolah_barcode(): void
    {
        // Regresi: mark() dulu menolak SEMUA via (termasuk kartu) begitu cara_absensi_guru
        // = barcode — padahal kamera kiosk kini sah membaca QR kartu di mode qr/keduanya.
        Setting::set('cara_absensi_guru', 'barcode');
        Setting::set('scan_kiosk_mode', 'qr');

        $this->actingAs($this->admin)
            ->postJson(route('absensi.markBarcode'), [
                'barcode' => '222222',
                'tanggal' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('absensis', [
            'id_siswa' => $this->siswaTanpaWajah->uuid,
            'status'   => 'hadir',
        ]);
    }

    public function test_kartu_qr_ditolak_saat_kamera_wajah_saja_dan_metode_barcode(): void
    {
        // Kombinasi terkunci penuh: metode sekolah barcode (wajah mati) + kamera kiosk
        // disetel wajah-saja (QR kamera mati) → kartu tidak boleh menandai hadir.
        Setting::set('cara_absensi_guru', 'barcode');
        Setting::set('scan_kiosk_mode', 'wajah');

        $this->actingAs($this->admin)
            ->postJson(route('absensi.markBarcode'), [
                'barcode' => '222222',
                'tanggal' => now()->toDateString(),
            ])
            ->assertOk()
            ->assertJsonPath('success', false);
    }

    public function test_via_wajah_ditolak_saat_kamera_disetel_qr_saja(): void
    {
        Setting::set('scan_kiosk_mode', 'qr');

        $this->actingAs($this->admin)
            ->postJson(route('absensi.mark'), [
                'id_siswa' => $this->siswaWajah->uuid,
                'id_kelas' => $this->kelas->uuid,
                'tanggal'  => now()->toDateString(),
                'status'   => 'hadir',
            ])
            ->assertOk()
            ->assertJsonPath('success', false);
    }

    public function test_nilai_setting_asing_di_database_jatuh_ke_keduanya(): void
    {
        Setting::set('scan_kiosk_mode', 'ngawur');

        $this->actingAs($this->admin)->get(route('absensi.scan'))
            ->assertOk()
            ->assertViewHas('scanKioskMode', 'keduanya');
    }
}
