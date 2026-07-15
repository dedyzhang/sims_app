<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ModulAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModulAktifTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'username' => 'admin_modul',
            'password' => Hash::make('password'),
            'access' => 'admin',
        ]);
    }

    private function guru(): User
    {
        return User::create([
            'username' => 'guru_modul',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
    }

    public function test_default_semua_modul_aktif(): void
    {
        foreach (ModulAktif::kodeValid() as $kode) {
            $this->assertTrue(ModulAktif::aktif($kode), "Modul {$kode} harus aktif by default");
        }
    }

    public function test_admin_bisa_simpan_toggle_fitur(): void
    {
        $admin = $this->admin();

        $payload = [];
        foreach (ModulAktif::kodeValid() as $kode) {
            // Matikan keuangan & chatbot; sisanya aktif
            if (! in_array($kode, ['keuangan', 'chatbot'], true)) {
                $payload[$kode] = '1';
            }
        }

        $this->actingAs($admin)
            ->post(route('setting.fitur'), $payload)
            ->assertRedirect();

        $this->assertFalse(ModulAktif::aktif('keuangan'));
        $this->assertFalse(ModulAktif::aktif('chatbot'));
        $this->assertTrue(ModulAktif::aktif('absensi'));
        $this->assertSame('0', Setting::get(ModulAktif::settingKey('keuangan')));
    }

    public function test_modul_off_blokir_url_dan_sembunyikan_menu(): void
    {
        Setting::set(ModulAktif::settingKey('asisten_guru'), '0');
        Setting::set(ModulAktif::settingKey('keuangan'), '0');

        $guru = $this->guru();

        $this->actingAs($guru)
            ->get(route('ai.teacher.index'))
            ->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)
            ->get('/keuangan')
            ->assertForbidden();

        $html = $this->actingAs($guru)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('Asisten Guru', $html);
    }

    public function test_modul_on_default_asisten_guru_bisa_dibuka(): void
    {
        $guru = $this->guru();

        $this->actingAs($guru)
            ->get(route('ai.teacher.index'))
            ->assertOk();
    }
}
