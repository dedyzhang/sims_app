<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Latar panel kiri halaman login: default (gradien bawaan), warna polos, atau gambar
 * unggahan dgn fokus/zoom yg bisa diatur admin (bukan crop pixel permanen — dipakai sbg
 * background-position/-size di CSS, tetap adaptif ke tinggi layar pengunjung berapa pun).
 */
class SettingLoginBackgroundTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'loginbg_admin', 'password' => Hash::make('password'), 'access' => 'admin']);
    }

    public function test_admin_bisa_ganti_ke_warna_polos(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('setting.loginBackground'), [
            'login_bg_type'  => 'color',
            'login_bg_color' => '#0f766e',
        ])->assertRedirect();

        $this->assertSame('color', Setting::get('login_bg_type'));
        $this->assertSame('#0f766e', Setting::get('login_bg_color'));
    }

    public function test_warna_harus_format_hex_valid(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('setting.loginBackground'), [
            'login_bg_type'  => 'color',
            'login_bg_color' => 'bukan-warna',
        ])->assertSessionHasErrors('login_bg_color');

        $this->assertNull(Setting::get('login_bg_type'));
    }

    public function test_admin_bisa_upload_gambar_dgn_fokus_dan_zoom(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('setting.loginBackground'), [
            'login_bg_type'    => 'image',
            'login_bg_image'   => UploadedFile::fake()->image('kampus.jpg', 800, 1200),
            'login_bg_focus_x' => 30,
            'login_bg_focus_y' => 70,
            'login_bg_zoom'    => 150,
        ])->assertRedirect();

        $this->assertSame('image', Setting::get('login_bg_type'));
        $path = Setting::get('login_bg_image');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('30', Setting::get('login_bg_focus_x'));
        $this->assertSame('70', Setting::get('login_bg_focus_y'));
        $this->assertSame('150', Setting::get('login_bg_zoom'));
    }

    public function test_hapus_gambar_kembali_kosong(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        Storage::disk('public')->put('login-bg/lama.jpg', 'dummy');
        Setting::set('login_bg_type', 'image');
        Setting::set('login_bg_image', 'login-bg/lama.jpg');

        $this->actingAs($admin)->post(route('setting.loginBackground'), [
            'login_bg_type'         => 'default',
            'hapus_login_bg_image'  => '1',
        ])->assertRedirect();

        $this->assertSame('', Setting::get('login_bg_image'));
        Storage::disk('public')->assertMissing('login-bg/lama.jpg');
    }

    public function test_bukan_admin_ditolak(): void
    {
        $guru = User::create(['username' => 'loginbg_guru', 'password' => Hash::make('password'), 'access' => 'guru']);

        $this->actingAs($guru)->post(route('setting.loginBackground'), [
            'login_bg_type' => 'color', 'login_bg_color' => '#000000',
        ])->assertForbidden();
    }

    public function test_halaman_login_pakai_gradien_default_saat_belum_diatur(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('from-blue-950', $html);
    }

    public function test_halaman_login_pakai_warna_kustom(): void
    {
        Setting::set('login_bg_type', 'color');
        Setting::set('login_bg_color', '#0f766e');

        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('background-color: #0f766e', $html);
        $this->assertStringNotContainsString('from-blue-950', $html);
    }

    public function test_halaman_login_pakai_gambar_dgn_posisi_dan_zoom(): void
    {
        // SENGAJA tak pakai Storage::fake('public') di sini — composer AppServiceProvider
        // ngecek keberadaan file via file_exists(storage_path(...)) langsung (sama spt pola
        // sekolah_logo yg sudah ada), yg TIDAK diarahkan ulang oleh Storage::fake(). Tulis
        // file sungguhan (root disk 'public' = storage_path('app/public') tanpa fake) &
        // bersihkan di akhir supaya tak mengotori disk lokal.
        Storage::disk('public')->put('login-bg/kustom_test.jpg', 'dummy');
        Setting::set('login_bg_type', 'image');
        Setting::set('login_bg_image', 'login-bg/kustom_test.jpg');
        Setting::set('login_bg_focus_x', '25');
        Setting::set('login_bg_focus_y', '80');
        Setting::set('login_bg_zoom', '140');

        try {
            // object-position + transform:scale (BUKAN background-size:%) — object-fit:cover
            // menghitung crop dari rasio ASLI gambar, konsisten walau bentuk kontainer beda
            // (dulu pakai background-size:% yg dihitung relatif ke kontainer SENDIRI, itu yg
            // bikin hasil di halaman login beda drastis dari pratinjau di halaman Pengaturan).
            $html = $this->get(route('login'))->assertOk()->getContent();
            $this->assertStringContainsString('object-position: 25% 80%', $html);
            $this->assertStringContainsString('transform: scale(1.4)', $html);
        } finally {
            Storage::disk('public')->delete('login-bg/kustom_test.jpg');
        }
    }

    /**
     * Kalau tipe di-set 'image' tapi filenya somehow hilang (dihapus manual dari disk,
     * atau race condition), halaman login harus tetap jatuh ke gradien default — bukan
     * merender background-image ke URL kosong/rusak.
     */
    public function test_fallback_ke_default_kalau_tipe_gambar_tapi_file_tak_ada(): void
    {
        Setting::set('login_bg_type', 'image');
        Setting::set('login_bg_image', 'login-bg/tidak-ada.jpg');

        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('from-blue-950', $html);
    }
}
