<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\ModulAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

class GrupChatModulTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    /**
     * Cari elemen nav-nya, bukan teks "Grup Chat" — string itu juga muncul di
     * komentar JS layout yang selalu dirender, sehingga assertDontSee pada teks
     * akan selalu gagal dan menyembunyikan regresi yang sesungguhnya.
     */
    private const PENANDA_MENU = 'data-tip="Grup Chat"';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    private function matikanModul(): void
    {
        Setting::set(ModulAktif::settingKey('grup_chat'), '0');
    }

    public function test_modul_aktif_secara_default(): void
    {
        $this->assertTrue(ModulAktif::aktif('grup_chat'));
        $this->assertContains('grup_chat', ModulAktif::kodeValid());
    }

    public function test_modul_off_memblokir_semua_endpoint(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $this->matikanModul();

        $this->actingAs($this->wali7a)->get(route('grup.index'))->assertForbidden();
        $this->actingAs($this->wali7a)->get(route('grup.show', $grup))->assertForbidden();
        $this->actingAs($this->wali7a)->getJson(route('grup.poll', $grup))->assertForbidden();
        $this->actingAs($this->wali7a)->getJson(route('grup.badge'))->assertForbidden();
        $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'halo'])
            ->assertForbidden();
    }

    public function test_modul_off_menyembunyikan_menu_sidebar_tanpa_merusak_dashboard(): void
    {
        $this->actingAs($this->wali7a)->get(route('dashboard'))->assertOk()
            ->assertSee(self::PENANDA_MENU, false);

        $this->matikanModul();

        $this->actingAs($this->wali7a)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(self::PENANDA_MENU, false);
    }

    public function test_menu_tersembunyi_untuk_user_tanpa_grup(): void
    {
        $bendahara = \App\Models\User::create([
            'username' => 'bendahara_menu',
            'password' => bcrypt('password'),
            'access' => 'bendahara',
        ]);

        $this->actingAs($bendahara)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(self::PENANDA_MENU, false);
    }
}
