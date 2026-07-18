<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\GeminiService;
use App\Support\ModulAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class AiAnalyzeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'username' => 'admin_analyze',
            'password' => Hash::make('password'),
            'access' => 'admin',
        ]);
    }

    public function test_analyze_page_shows_school_key_notice_when_disabled(): void
    {
        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('enabled')->andReturn(false);
        });

        $this->actingAs($this->admin())
            ->get(route('ai.analyze.index'))
            ->assertOk()
            ->assertSee('kunci AI sekolah', false);
    }

    public function test_absensi_without_school_ai_returns_friendly_error_after_data_ok(): void
    {
        $siswa = \App\Models\Siswa::create([
            'nis' => 'RAG001',
            'nama' => 'Siswa Test',
            'id_kelas' => null,
        ]);
        \App\Models\Absensi::create([
            'id_siswa' => $siswa->uuid,
            'tanggal' => '2026-07-01',
            'status' => 'H',
        ]);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('enabled')->andReturn(false);
        });

        $this->actingAs($this->admin())
            ->postJson(route('ai.analyze.absensi'), [
                'dari' => '2026-07-01',
                'sampai' => '2026-07-31',
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonFragment(['message' => 'Narasi Data memakai kunci AI sekolah di server (.env). Minta admin mengisi GEMINI_API_KEY atau OpenRouter — berbeda dari API key pribadi Asisten Guru.']);
    }

    public function test_keuangan_blocked_when_modul_off(): void
    {
        Setting::set(ModulAktif::settingKey('keuangan'), '0');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('ai.analyze.keuangan'), [
                'tahun_ajaran' => '2025/2026',
            ])
            ->assertForbidden()
            ->assertJsonPath('ok', false);

        $html = $this->actingAs($admin)
            ->get(route('ai.analyze.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString("key: 'keuangan'", $html);
    }
}
