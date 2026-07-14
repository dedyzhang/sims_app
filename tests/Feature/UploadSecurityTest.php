<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Hardening upload (hasil audit keamanan):
 *  - Patch #1: logo sekolah TIDAK boleh SVG (SVG bisa memuat <script> → stored XSS
 *    saat file di disk publik dibuka langsung). PNG/JPG/WEBP tetap diterima.
 *  - Patch #2: endpoint upload chatbot & balasan admin ber-throttle (cegah
 *    disk-fill/DoS oleh user terautentikasi).
 */
class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        // Tanpa profil siswa/guru → lolos gate EnsureFaceRegistered.
        return User::create([
            'username' => 'up_sec_admin',
            'password' => Hash::make('password'),
            'access'   => 'superadmin',
        ]);
    }

    // ─────────────── Patch #1: SVG logo ditolak ───────────────

    public function test_upload_logo_svg_ditolak(): void
    {
        Storage::fake('public');

        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>'
        );

        $this->actingAs($this->superadmin())
            ->post(route('setting.identitas'), ['sekolah_logo' => $svg])
            ->assertSessionHasErrors('sekolah_logo');

        // Tidak ada file yang tersimpan ke folder logo.
        $this->assertEmpty(Storage::disk('public')->files('logo'));
    }

    public function test_upload_logo_png_tetap_diterima(): void
    {
        Storage::fake('public');

        $png = UploadedFile::fake()->image('logo.png', 200, 200);

        $this->actingAs($this->superadmin())
            ->post(route('setting.identitas'), ['sekolah_logo' => $png])
            ->assertSessionHasNoErrors();

        // Regresi: logo valid tetap tersimpan.
        $this->assertNotEmpty(Storage::disk('public')->files('logo'));
    }

    // ─────────────── Patch #2: throttle terpasang ───────────────

    public function test_endpoint_upload_chatbot_ber_throttle(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertContains('throttle:30,1', $routes->getByName('chatbot.upload')->gatherMiddleware());
        $this->assertContains('throttle:30,1', $routes->getByName('chatbot.upload-file')->gatherMiddleware());
        $this->assertContains('throttle:30,1', $routes->getByName('chatbot.send')->gatherMiddleware());
        $this->assertContains('throttle:60,1', $routes->getByName('chatbot.poll')->gatherMiddleware());
        $this->assertContains('throttle:60,1', $routes->getByName('chatbot.admin.reply-image')->gatherMiddleware());
        $this->assertContains('throttle:60,1', $routes->getByName('chatbot.admin.reply-file')->gatherMiddleware());
    }
}
