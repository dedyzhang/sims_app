<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\GeminiService;
use App\Support\ModulAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
            ->assertSee('Fitur narasi belum siap', false);
    }

    public function test_analyze_page_allows_personal_key_without_school_key(): void
    {
        config()->set('ai.api_key', '');
        config()->set('ai.provider', 'gemini');
        config()->set('ai.fallback_providers', []);

        $admin = $this->admin();
        $admin->forceFill([
            'gemini_api_key' => Crypt::encryptString('AIzaSyPersonalAnalyzeFeatureTest01'),
            'gemini_api_key_hint' => 'st01',
        ])->save();

        $this->actingAs($admin)
            ->get(route('ai.analyze.index'))
            ->assertOk()
            ->assertSee('menyusun narasi siap pakai', false)
            ->assertDontSee('Fitur narasi belum siap', false);
    }

    public function test_absensi_without_any_ai_key_returns_friendly_error_after_data_ok(): void
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
            ->assertJsonFragment(['message' => 'Fitur narasi belum siap. Lengkapi pengaturan akun atau minta admin mengaktifkan konfigurasi sekolah.']);
    }

    public function test_absensi_uses_personal_key_before_school_key(): void
    {
        config()->set('ai.api_key', 'school-gemini-key');
        config()->set('ai.provider', 'gemini');
        config()->set('ai.fallback_providers', []);

        $plain = 'AIzaSyPersonalAnalyzeFeatureTest01';
        $admin = $this->admin();
        $admin->forceFill([
            'gemini_api_key' => Crypt::encryptString($plain),
            'gemini_api_key_hint' => 'st01',
        ])->save();

        $siswa = \App\Models\Siswa::create([
            'nis' => 'ANL001',
            'nama' => 'Siswa Test',
            'id_kelas' => null,
        ]);
        \App\Models\Absensi::create([
            'id_siswa' => $siswa->uuid,
            'tanggal' => '2026-07-01',
            'status' => 'H',
        ]);

        $this->mock(GeminiService::class, function (MockInterface $mock) use ($plain) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt, array $options) => str_contains($prompt, 'Rekap kehadiran')
                    && ($options['api_key'] ?? null) === $plain)
                ->andReturn([
                    'text' => 'Kehadiran stabil.',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 10,
                    'completion_tokens' => 8,
                ]);
        });

        $this->actingAs($admin)
            ->postJson(route('ai.analyze.absensi'), [
                'dari' => '2026-07-01',
                'sampai' => '2026-07-31',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('answer', 'Kehadiran stabil.');
    }

    public function test_keuangan_api_blocked_when_modul_off(): void
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

        $this->assertStringNotContainsString('Catatan SPP', $html);
    }
}
