<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ModeUjian;
use App\Support\PollingWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Toggle "Panggilan Latar Aplikasi & Notifikasi" di Pengaturan → Performa Server:
 * - notifikasi        → GET /notifications-json (bel notifikasi)
 * - fcm_token         → POST /notifications/fcm-token (pendaftaran HP utk push notification)
 * - firebase_realtime → POST /firebase/custom-token + SDK Firebase di halaman
 * Data log produksi 26-11-2026: ketiganya ±47% dari seluruh request PHP. Saat Mode Ujian
 * aktif ketiganya otomatis berhenti.
 */
class PanggilanLatarToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        // Supaya blok Firebase di layout memenuhi syarat konfigurasi.
        config([
            'services.firebase.api_key' => 'kunci-uji',
            'services.firebase.database_url' => 'https://uji.firebaseio.com',
        ]);
    }

    private function admin(): User
    {
        return User::create(['username' => 'admin_panggilan_latar', 'password' => Hash::make('password'), 'access' => 'superadmin']);
    }

    public function test_ketiga_toggle_ada_di_daftar_dan_aktif_secara_bawaan(): void
    {
        foreach (['notifikasi', 'fcm_token', 'firebase_realtime'] as $kode) {
            $this->assertContains($kode, PollingWidget::kodeValid());
            $this->assertTrue(PollingWidget::aktif($kode), "{$kode} harus aktif bawaan");
            $this->assertSame('Panggilan Latar Aplikasi & Notifikasi', PollingWidget::semua()[$kode]['kelompok']);
        }
    }

    public function test_halaman_pengaturan_menampilkan_ketiga_toggle(): void
    {
        $this->actingAs($this->admin())->get(route('setting.index'))
            ->assertOk()
            ->assertSee('Panggilan Latar Aplikasi &amp; Notifikasi', false)
            ->assertSee('name="fcm_token"', false)
            ->assertSee('name="firebase_realtime"', false)
            ->assertSee('name="notifikasi"', false);
    }

    public function test_admin_bisa_menonaktifkan_lalu_mengaktifkan_kembali(): void
    {
        $admin = $this->admin();
        $semuaKecuali = fn (array $mati) => collect(PollingWidget::kodeValid())
            ->reject(fn ($k) => in_array($k, $mati, true))
            ->mapWithKeys(fn ($k) => [$k => '1'])->all();

        $this->actingAs($admin)->post(route('setting.pollingNonaktif'), $semuaKecuali(['fcm_token', 'firebase_realtime']))->assertRedirect();
        $this->assertTrue(PollingWidget::nonaktif('fcm_token'));
        $this->assertTrue(PollingWidget::nonaktif('firebase_realtime'));
        $this->assertTrue(PollingWidget::aktif('notifikasi'));

        $this->actingAs($admin)->post(route('setting.pollingNonaktif'), $semuaKecuali([]))->assertRedirect();
        $this->assertTrue(PollingWidget::aktif('fcm_token'));
        $this->assertTrue(PollingWidget::aktif('firebase_realtime'));
    }

    public function test_fcm_token_nonaktif_dikirim_ke_halaman_dan_dijaga_di_fungsi_pendaftaran(): void
    {
        Setting::set(PollingWidget::settingKey('fcm_token'), '0');

        $html = $this->actingAs($this->admin())->get(route('setting.index'))->assertOk()->getContent();

        $this->assertStringContainsString('window.SIMS_POLLING_NONAKTIF = ["fcm_token"]', $html);
        // Penjaga ada di dalam registerFcmToken (yg juga bisa dipanggil langsung oleh aplikasi Android).
        $this->assertMatchesRegularExpression("/window\\.registerFcmToken = function[\\s\\S]{0,900}simsPollingNonaktif\\('fcm_token'\\)/", $html);
    }

    public function test_firebase_realtime_nonaktif_tidak_memuat_sdk_maupun_custom_token(): void
    {
        $admin = $this->admin();

        $aktif = $this->actingAs($admin)->get(route('setting.index'))->assertOk();
        $aktif->assertSee(route('firebase.token'), false);
        $aktif->assertSee('window.simsFirebase = {', false);

        Setting::set(PollingWidget::settingKey('firebase_realtime'), '0');

        $mati = $this->actingAs($admin)->get(route('setting.index'))->assertOk();
        $mati->assertDontSee(route('firebase.token'), false);
        $mati->assertDontSee('window.simsFirebase = {', false);
    }

    public function test_mode_ujian_otomatis_mematikan_ketiganya(): void
    {
        ModeUjian::aktifkan();

        foreach (['notifikasi', 'fcm_token', 'firebase_realtime'] as $kode) {
            $this->assertTrue(PollingWidget::nonaktif($kode), "{$kode} harus nonaktif saat Mode Ujian");
            $this->assertContains($kode, PollingWidget::daftarNonaktif());
        }
    }

    public function test_firebase_memakai_ulang_sesi_per_tab_tanpa_iframe_google(): void
    {
        $admin = $this->admin();
        $html = $this->actingAs($admin)->get(route('setting.index'))->assertOk()->getContent();

        // initializeAuth + sessionStorage, BUKAN getAuth() (yg memuat iframe Google & IndexedDB permanen).
        $this->assertStringContainsString('initializeAuth(app, { persistence: browserSessionPersistence })', $html);
        $this->assertStringNotContainsString('getAuth(app)', $html);
        // custom-token hanya diminta bila sesi tersimpan bukan milik user ini (uid/role/school_id).
        $this->assertStringContainsString('uid: "' . $admin->uuid . '"', $html);
        $this->assertStringContainsString('role: "superadmin"', $html);
        $this->assertMatchesRegularExpression('/u\.uid === identitasDiharapkan\.uid[\s\S]{0,600}siapkan\(\);[\s\S]{0,400}signOut\(auth\)[\s\S]{0,80}mintaTokenLaluMasuk\(\)/', $html);
        // Logout menghapus sesi Firebase tab ini secara sinkron.
        $this->assertMatchesRegularExpression("/form\\[action\\*=\"logout\"\\][\\s\\S]{0,1200}indexOf\\('firebase:'\\) === 0/", $html);
    }

    public function test_suara_notifikasi_tidak_diunduh_saat_halaman_dibuka(): void
    {
        $html = $this->actingAs($this->admin())->get(route('setting.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString("this.audio.preload = 'auto'", $html);
        // File suara baru dibuat di dalam playSound(), bukan di init().
        $this->assertMatchesRegularExpression("/playSound\\(\\) \\{[\\s\\S]{0,300}new Audio\\('[^']*notif-sims\\.wav'\\)/", $html);
    }

    public function test_bel_notifikasi_meminta_json_supaya_tidak_dialihkan_ke_halaman_penuh(): void
    {
        $html = $this->actingAs($this->admin())->get(route('setting.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression("/fetch\\('[^']*notifications-json',\\s*\\{\\s*headers: \\{ 'Accept': 'application\\/json'/", $html);
    }
}
