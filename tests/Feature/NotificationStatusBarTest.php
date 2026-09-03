<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * notifications.json dulu dipoll SENDIRIAN oleh bel — sekarang badge grup chat, chatbot,
 * chat-admin, dan masukan numpang di response yang sama (NotificationController::
 * badgesLainnya()), gantikan 4 fetch terpisah yang dulu nembak bersamaan tiap halaman
 * dimuat & tiap interval (lihat layouts/app.blade.php). Test ini mengunci field mana yang
 * disertakan per role — harus persis sama dengan syarat @if yang dulu membungkus tiap
 * widget di layout, supaya konsolidasi ini tidak diam-diam mengubah siapa lihat apa.
 */
class NotificationStatusBarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
    }

    private function superadmin(): User
    {
        return User::create([
            'username' => 'statusbar_admin',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);
    }

    private function siswaUser(): User
    {
        return User::create([
            'username' => 'statusbar_siswa',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
    }

    public function test_admin_dapat_badge_admin_grup_dan_masukan_tapi_bukan_chatbot(): void
    {
        $admin = $this->superadmin();

        $response = $this->actingAs($admin)->getJson(route('notifications.json'))->assertOk();

        $response->assertJsonStructure(['adminChatUnread', 'grupUnread', 'feedbackUnread']);
        $this->assertArrayNotHasKey('chatbotUnread', $response->json());
    }

    public function test_siswa_dapat_badge_chatbot_tapi_bukan_admin_grup_atau_masukan(): void
    {
        $siswa = $this->siswaUser();

        $response = $this->actingAs($siswa)->getJson(route('notifications.json'))->assertOk();

        $response->assertJsonStructure(['chatbotUnread']);
        $data = $response->json();
        $this->assertArrayNotHasKey('adminChatUnread', $data);
        $this->assertArrayNotHasKey('feedbackUnread', $data);
        // Siswa tanpa keanggotaan grup manapun: menu grup chat tak layak tampil (GrupChatMenu::tampil()).
        $this->assertArrayNotHasKey('grupUnread', $data);
    }

    public function test_badge_hilang_saat_modul_terkait_dimatikan(): void
    {
        $admin = $this->superadmin();
        Setting::set(\App\Support\ModulAktif::settingKey('grup_chat'), '0');

        $response = $this->actingAs($admin)->getJson(route('notifications.json'))->assertOk();

        $this->assertArrayNotHasKey('grupUnread', $response->json());
    }
}
