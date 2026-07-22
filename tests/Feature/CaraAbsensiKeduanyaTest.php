<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Support\AbsensiGuru;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Opsi ke-3 metode absensi sekolah (cara_absensi_guru): 'keduanya' — wajah DAN barcode/QR
 * aktif bersamaan (dulu hanya bisa pilih salah satu, metode lain otomatis dikunci).
 */
class CaraAbsensiKeduanyaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('kaih_wajib_sebelum_absen', '0');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::create([
            'username' => 'admin_cara_keduanya',
            'password' => Hash::make('x'),
            'access' => 'superadmin',
        ]);
    }

    public function test_mode_keduanya_membuka_wajah_dan_qr_sekaligus(): void
    {
        Setting::set('cara_absensi_guru', 'keduanya');

        $this->assertTrue(AbsensiGuru::bolehWajah());
        $this->assertTrue(AbsensiGuru::bolehQr());
    }

    public function test_mode_wajah_saja_tetap_mengunci_qr(): void
    {
        Setting::set('cara_absensi_guru', 'wajah');

        $this->assertTrue(AbsensiGuru::bolehWajah());
        $this->assertFalse(AbsensiGuru::bolehQr());
    }

    public function test_mode_barcode_saja_tetap_mengunci_wajah(): void
    {
        Setting::set('cara_absensi_guru', 'barcode');

        $this->assertFalse(AbsensiGuru::bolehWajah());
        $this->assertTrue(AbsensiGuru::bolehQr());
    }

    public function test_setting_menerima_nilai_keduanya(): void
    {
        $this->actingAs($this->admin())
            ->post(route('setting.caraAbsensi'), ['cara_absensi' => 'keduanya'])
            ->assertRedirect();

        $this->assertSame('keduanya', Setting::get('cara_absensi_guru'));
    }

    public function test_setting_menolak_nilai_asing(): void
    {
        $this->actingAs($this->admin())
            ->post(route('setting.caraAbsensi'), ['cara_absensi' => 'ngawur'])
            ->assertSessionHasErrors('cara_absensi');
    }

    public function test_halaman_setting_menampilkan_3_opsi(): void
    {
        Setting::set('cara_absensi_guru', 'keduanya');

        $this->actingAs($this->admin())
            ->get(route('setting.index'))
            ->assertOk()
            ->assertSee('Wajah + Barcode/QR');
    }

    public function test_kiosk_enter_mode_keduanya_ke_kamera_scan_bukan_poster_qr(): void
    {
        // Kiosk 'keduanya' harus ke kamera (baca wajah + kartu QR), BUKAN ke halaman poster QR
        // statis — beda dgn mode 'barcode' murni yg memang hanya menampilkan poster.
        Setting::set('cara_absensi_guru', 'keduanya');
        Setting::set('kiosk_token', 'tok-keduanya-123');

        $this->get(route('absensi.kioskEnter', 'tok-keduanya-123'))
            ->assertRedirect(route('absensi.scan') . '?_kiosk=tok-keduanya-123');
    }

    public function test_kiosk_enter_mode_barcode_tetap_ke_poster_qr(): void
    {
        Setting::set('cara_absensi_guru', 'barcode');
        Setting::set('kiosk_token', 'tok-barcode-123');

        $this->get(route('absensi.kioskEnter', 'tok-barcode-123'))
            ->assertRedirect(route('qr.absensi') . '?_kiosk=tok-barcode-123');
    }

    public function test_siswa_bisa_absen_via_qr_poster_saat_mode_keduanya(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-22 07:10:00'));
        Setting::set('cara_absensi_guru', 'keduanya');
        Setting::set('qr_absensi_aktif', '1');
        Setting::set('sekolah_lat', '-6.200000');
        Setting::set('sekolah_lng', '106.816666');
        Setting::set('absen_radius', '100');
        Setting::set('sekolah_geo_points', '[]');
        Setting::set('absen_rush_bonus', '0');

        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $siswaUser = User::create(['username' => 'siswa_keduanya_qr', 'password' => Hash::make('x'), 'access' => 'siswa']);
        $siswa = Siswa::create([
            'id_login' => $siswaUser->uuid, 'id_kelas' => $kelas->uuid,
            'nama' => 'Siswa Keduanya QR', 'nis' => '333001', 'jk' => 'L',
        ]);

        $token = substr(hash_hmac('sha256', 'qrabsen|'.now()->toDateString(), (string) config('app.key')), 0, 12);

        $this->actingAs($siswaUser)->postJson(route('absen.qr.mark'), [
            'token' => $token,
            'lat' => '-6.200000',
            'lng' => '106.816666',
            'accuracy' => 25,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('absensis', ['id_siswa' => $siswa->uuid, 'status' => 'hadir']);
    }

    public function test_guru_bisa_absen_via_wajah_kiosk_saat_mode_keduanya(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 07:10:00'));
        Setting::set('cara_absensi_guru', 'keduanya');
        Setting::set('scan_kiosk_mode', 'keduanya');

        $guruUser = User::create(['username' => 'guru_keduanya_wajah', 'password' => Hash::make('x'), 'access' => 'guru']);
        $guru = Guru::create([
            'id_login' => $guruUser->uuid, 'nama' => 'Guru Keduanya Wajah',
            'nik' => '5501199', 'nip' => '198501012010011099', 'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);

        $this->actingAs($this->admin())->postJson(route('presensi-guru.mark'), [
            'id_guru' => $guru->uuid,
            'tanggal' => '2026-07-22',
            'mode' => 'masuk',
            '_via' => 'face',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('presensi_gurus', [
            'id_guru' => $guru->uuid,
            'status' => 'hadir',
        ]);
    }

    public function test_presensi_saya_menampilkan_toggle_wajah_qr_saat_mode_keduanya(): void
    {
        Setting::set('cara_absensi_guru', 'keduanya');
        Setting::set('sekolah_lat', '-6.200000');
        Setting::set('sekolah_lng', '106.816666');

        $guruUser = User::create(['username' => 'guru_keduanya_self', 'password' => Hash::make('x'), 'access' => 'guru']);
        $guru = Guru::create([
            'id_login' => $guruUser->uuid, 'nama' => 'Guru Keduanya Self',
            'nik' => '5501200', 'nip' => '198501012010011100', 'jk' => 'L',
            'face_descriptor' => [[0.1, 0.2]],
        ]);
        // Kartu "Izin Pulang Awal" hanya muncul kalau sudah absen masuk & belum pulang hari ini.
        \App\Models\PresensiGuru::create([
            'id_guru' => $guru->uuid, 'tanggal' => now()->toDateString(),
            'jam_masuk' => '07:00:00', 'status' => 'hadir',
        ]);

        $res = $this->actingAs($guruUser)->get(route('presensi-guru.self'))->assertOk();

        // Toggle & kedua jalur (skrip Human.js utk wajah + html5-qrcode utk QR) sama2 dimuat.
        $res->assertSee('metodeIzin', false);
        $res->assertSee('izinPulangQr(', false);
        $res->assertSee('izinPulang(', false);
        $res->assertSee('human.js', false);
        $res->assertSee('html5-qrcode', false);
    }
}
