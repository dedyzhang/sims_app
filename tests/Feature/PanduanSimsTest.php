<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanduanSimsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access = 'siswa'): User
    {
        return User::create([
            'username' => 'panduan_'.$access,
            'password' => Hash::make('password'),
            'access' => $access,
        ]);
    }

    public function test_guest_diarahkan_ke_login(): void
    {
        $this->get('/panduan-sims')->assertRedirect(route('login'));
        $this->get('/panduan-sims/konten')->assertRedirect(route('login'));
    }

    public function test_user_tanpa_data_wajah_tetap_bisa_membuka_panduan_visual(): void
    {
        $user = $this->makeUser('siswa');

        $this->actingAs($user)
            ->get('/panduan-sims')
            ->assertOk()
            ->assertSee('Panduan Visual', false)
            ->assertSee(route('panduan.content'), false)
            ->assertSee('<iframe', false);
    }

    public function test_konten_panduan_visual_tersedia_untuk_iframe(): void
    {
        $user = $this->makeUser('siswa');

        $this->actingAs($user)
            ->get('/panduan-sims/konten')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('Arena Belajar', false)
            ->assertSee('Misi Edukatif', false)
            ->assertSee('mode=misi', false)
            ->assertSee('SD / SMP / SMA-SMK', false)
            ->assertSee('Tren 2025–2026', false)
            ->assertSee('Deepfake di Dunia Kerja', false)
            ->assertSee('Prompt Cerdas', false)
            ->assertSee('arenatren', false)
            ->assertSee('arenakuis', false)
            ->assertSee('Ular tangga', false)
            ->assertSee('Solo · soal acak', false)
            ->assertSee('Salin soal ke kelas lain', false)
            ->assertSee('Kirim ke Arena', false)
            ->assertSee('Nalar Guru & Kuota', false)
            ->assertSee('/images/panduan/', false)
            ->assertSee('/videos/panduan/', false)
            ->assertSee('Asisten Guru', false)
            ->assertSee('Hubungkan API key', false)
            ->assertSee('/ai/teacher', false)
            ->assertSee('asisten-ai-s1.png', false)
            ->assertSee('asisten-ai-s5.png', false)
            ->assertSee('Urutan Screenshot Storyboard', false)
            ->assertSee('PANDUAN_CTX', false)
            ->assertSee('"access":"siswa"', false)
            ->assertSee('"isAdmin":false', false)
            ->assertSee('html.dark', false)
            ->assertSee('theme_mode', false)
            ->assertSee('sims-theme', false);
    }

    public function test_konten_admin_mendapat_flag_is_admin(): void
    {
        $user = $this->makeUser('admin');

        $this->actingAs($user)
            ->get('/panduan-sims/konten')
            ->assertOk()
            ->assertSee('"isAdmin":true', false)
            ->assertSee('"access":"admin"', false)
            ->assertSee('roleActions', false)
            ->assertSee('roleChecks', false);
    }

    public function test_url_visual_lama_diarahkan_ke_panduan(): void
    {
        $user = $this->makeUser('admin');

        $this->actingAs($user)
            ->get('/panduan-sims/visual')
            ->assertRedirect('/panduan-sims');
    }

    public function test_video_panduan_tersedia_di_public(): void
    {
        $this->assertFileExists(public_path('videos/panduan/login.mp4'));
        $this->assertFileExists(public_path('videos/panduan/dashboard.mp4'));
        $this->assertFileExists(public_path('videos/panduan/ai.mp4'));
    }
}
