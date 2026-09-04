<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\PollingWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tab "Performa Server" — pengganti mode darurat 1-tombol: admin matikan widget polling
 * SATU-PER-SATU (App\Support\PollingWidget), dikirim ke JS sbg window.SIMS_POLLING_NONAKTIF
 * dan dicek per-kode di tiap window.simsPollInterval(fn, ms, kode).
 *
 * Semantik checkbox: TERCENTANG = aktif/normal (bawaan, Setting default '1'). Checkbox HTML
 * yg tak dicentang TIDAK ikut terkirim di form submit sungguhan — jadi kode yg diOMIT dari
 * request di sini mensimulasikan "user uncheck", bukan kode yg dikirim eksplisit '0'.
 */
class SettingPollingNonaktifTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
    }

    private function admin(): User
    {
        return User::create([
            'username' => 'polling_nonaktif_admin',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);
    }

    private function siswaUser(): User
    {
        return User::create([
            'username' => 'polling_nonaktif_siswa',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
    }

    public function test_semua_widget_aktif_secara_bawaan_tanpa_setting_apapun(): void
    {
        foreach (PollingWidget::kodeValid() as $kode) {
            $this->assertTrue(PollingWidget::aktif($kode), "kode {$kode} harusnya aktif bawaan");
            $this->assertFalse(PollingWidget::nonaktif($kode));
        }
    }

    public function test_uncheck_beberapa_widget_mematikannya_yang_lain_tetap_aktif(): void
    {
        $admin = $this->admin();

        // Simulasi form disubmit dgn 'notifikasi' & 'arena_live' DI-UNCHECK (diomit dari
        // request — persis spt browser sungguhan tak mengirim checkbox yg tak dicentang),
        // sisanya tetap dicentang/dikirim '1'.
        $semuaKecuali = collect(PollingWidget::kodeValid())
            ->reject(fn (string $k) => in_array($k, ['notifikasi', 'arena_live'], true))
            ->mapWithKeys(fn (string $k) => [$k => '1'])
            ->all();

        $response = $this->actingAs($admin)->post(route('setting.pollingNonaktif'), $semuaKecuali);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue(PollingWidget::nonaktif('notifikasi'));
        $this->assertTrue(PollingWidget::nonaktif('arena_live'));
        $this->assertFalse(PollingWidget::nonaktif('ticker'));
        $this->assertFalse(PollingWidget::nonaktif('komentar_kelas'));
        $this->assertFalse(PollingWidget::nonaktif('arena_latihan'));
    }

    public function test_check_ulang_mengaktifkan_kembali_widget(): void
    {
        $admin = $this->admin();
        Setting::set(PollingWidget::settingKey('notifikasi'), '0'); // sebelumnya nonaktif

        // Submit dgn 'notifikasi' dicentang lagi (dikirim '1'), semua lain juga dicentang.
        $semua = collect(PollingWidget::kodeValid())->mapWithKeys(fn (string $k) => [$k => '1'])->all();
        $this->actingAs($admin)->post(route('setting.pollingNonaktif'), $semua)->assertRedirect();

        $this->assertTrue(PollingWidget::aktif('notifikasi'));
    }

    public function test_submit_kosong_mematikan_semua_widget(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('setting.pollingNonaktif'), [])->assertRedirect();

        foreach (PollingWidget::kodeValid() as $kode) {
            $this->assertTrue(PollingWidget::nonaktif($kode), "kode {$kode} harusnya nonaktif setelah submit kosong");
        }
    }

    public function test_non_admin_tidak_bisa_ubah_performa_server(): void
    {
        $siswa = $this->siswaUser();

        $this->actingAs($siswa)->post(route('setting.pollingNonaktif'), [
            'notifikasi' => '1',
        ])->assertForbidden();

        // Tak ada perubahan sama sekali — masih default aktif.
        $this->assertTrue(PollingWidget::aktif('notifikasi'));
    }

    public function test_flag_global_di_layout_cuma_berisi_kode_yang_nonaktif(): void
    {
        $admin = $this->admin();
        Setting::set(PollingWidget::settingKey('ticker'), '0');
        Setting::set(PollingWidget::settingKey('komentar_kelas'), '0');

        $response = $this->actingAs($admin)->get(route('setting.index'))->assertOk();

        $response->assertSee('window.SIMS_POLLING_NONAKTIF = ["ticker","komentar_kelas"]', false);
    }

    public function test_checkbox_tercentang_sesuai_status_aktif_di_halaman(): void
    {
        $admin = $this->admin();
        Setting::set(PollingWidget::settingKey('ticker'), '0'); // nonaktif -> checkbox TAK tercentang

        $response = $this->actingAs($admin)->get(route('setting.index'))->assertOk();
        $html = $response->getContent();

        // notifikasi aktif (bawaan) -> checkbox tercentang; cek atribut 'checked' muncul
        // pada baris <input ... name="notifikasi" ...>.
        $this->assertMatchesRegularExpression('/name="notifikasi"[^>]*checked/', $html);
        // ticker dimatikan -> checkbox TIDAK boleh punya 'checked' pada tag inputnya.
        $this->assertDoesNotMatchRegularExpression('/name="ticker"[^>]*checked/', $html);
    }

    public function test_ujian_dan_pemantauan_ruangan_tidak_pernah_ada_di_daftar_kanonik(): void
    {
        $kode = PollingWidget::kodeValid();

        foreach (['ujian', 'ujian_kerjakan', 'ujian_monitor', 'pemantauan_ruangan_ujian'] as $kodeUjian) {
            $this->assertNotContains($kodeUjian, $kode);
        }
    }
}
