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

    public function test_admin_bisa_matikan_beberapa_widget_sekaligus(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('setting.pollingNonaktif'), [
            'notifikasi' => '1',
            'ticker' => '1',
            'arena_live' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue(PollingWidget::nonaktif('notifikasi'));
        $this->assertTrue(PollingWidget::nonaktif('ticker'));
        $this->assertTrue(PollingWidget::nonaktif('arena_live'));
        // Yang tak dicentang harus tetap aktif (bukan ikut kena '1' krn ada di request lain).
        $this->assertFalse(PollingWidget::nonaktif('komentar_kelas'));
        $this->assertFalse(PollingWidget::nonaktif('arena_latihan'));
    }

    public function test_uncheck_mengaktifkan_kembali_widget(): void
    {
        $admin = $this->admin();
        Setting::set(PollingWidget::settingKey('notifikasi'), '1');

        $this->actingAs($admin)->post(route('setting.pollingNonaktif'), [])->assertRedirect();

        $this->assertFalse(PollingWidget::nonaktif('notifikasi'));
    }

    public function test_non_admin_tidak_bisa_ubah_performa_server(): void
    {
        $siswa = $this->siswaUser();

        $this->actingAs($siswa)->post(route('setting.pollingNonaktif'), [
            'notifikasi' => '1',
        ])->assertForbidden();

        $this->assertFalse(PollingWidget::nonaktif('notifikasi'));
    }

    public function test_flag_global_di_layout_cuma_berisi_kode_yang_nonaktif(): void
    {
        $admin = $this->admin();
        Setting::set(PollingWidget::settingKey('ticker'), '1');
        Setting::set(PollingWidget::settingKey('komentar_kelas'), '1');

        $response = $this->actingAs($admin)->get(route('setting.index'))->assertOk();

        $response->assertSee('window.SIMS_POLLING_NONAKTIF = ["ticker","komentar_kelas"]', false);
    }

    public function test_ujian_dan_pemantauan_ruangan_tidak_pernah_ada_di_daftar_kanonik(): void
    {
        $kode = PollingWidget::kodeValid();

        foreach (['ujian', 'ujian_kerjakan', 'ujian_monitor', 'pemantauan_ruangan_ujian'] as $kodeUjian) {
            $this->assertNotContains($kodeUjian, $kode);
        }
    }
}
