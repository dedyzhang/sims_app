<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Orangtua;
use App\Models\Setting;
use App\Models\Siswa;
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

    public function test_arena_belajar_falls_back_to_legacy_jagat_toggle(): void
    {
        Setting::where('key', ModulAktif::settingKey('arena_belajar'))->delete();
        Setting::set('fitur_jagat_misi_aktif', '0');

        $this->assertFalse(ModulAktif::aktif('arena_belajar'));

        Setting::set('fitur_jagat_misi_aktif', '1');
        $this->assertTrue(ModulAktif::aktif('arena_belajar'));
    }

    public function test_arena_belajar_row_overrides_legacy_jagat_toggle(): void
    {
        Setting::set(ModulAktif::settingKey('arena_belajar'), '0');
        Setting::set('fitur_jagat_misi_aktif', '1');

        $this->assertFalse(ModulAktif::aktif('arena_belajar'));
    }

    public function test_sumber_modul_aktif_bebas_marker_konflik_git(): void
    {
        $source = file_get_contents(base_path('app/Support/ModulAktif.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('<<<<<<<', $source);
        $this->assertStringNotContainsString('>>>>>>>', $source);
        $this->assertStringNotContainsString('Current (Your changes)', $source);
    }

    /** Sample wajah dummy — cukup utk lolos gate EnsureFaceRegistered (bukan diuji akurasinya di sini). */
    private function dummyFace(): array
    {
        return [array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63))];
    }

    /** @return array<string, User> peran => user, dgn profil siswa/guru/orangtua lengkap (lolos gate wajah). */
    private function satuUserPerPeran(): array
    {
        $admin = User::create(['username' => 'modul_admin', 'password' => Hash::make('x'), 'access' => 'admin']);

        $guruUser = User::create(['username' => 'modul_guru', 'password' => Hash::make('x'), 'access' => 'guru']);
        Guru::create([
            'id_login' => $guruUser->getKey(),
            'nama' => 'Guru Modul Test',
            'nik' => 'MODGURU001',
            'face_descriptor' => $this->dummyFace(),
        ]);

        $siswaUser = User::create(['username' => 'modul_siswa', 'password' => Hash::make('x'), 'access' => 'siswa']);
        $siswa = Siswa::create([
            'id_login' => $siswaUser->getKey(),
            'nama' => 'Siswa Modul Test',
            'nis' => 'MODSISWA001',
            'jk' => 'L',
            'face_descriptor' => $this->dummyFace(),
        ]);

        $ortuUser = User::create(['username' => 'modul_ortu', 'password' => Hash::make('x'), 'access' => 'orangtua']);
        Orangtua::create(['id_siswa' => $siswa->uuid, 'id_login' => $ortuUser->getKey()]);

        return ['admin' => $admin, 'guru' => $guruUser, 'siswa' => $siswaUser, 'orangtua' => $ortuUser];
    }

    /**
     * Regresi: menu sidebar (layouts/app.blade.php) dibangun lewat satu blok @php besar yang
     * bercabang per modul ($modulOn('xxx')). Kalau ada variabel yang didefinisikan DI DALAM
     * satu cabang modul tapi dipakai lagi DI LUAR cabang itu (mis. bug $bolehKelolaDisiplin
     * yg sempat bikin dashboard 500 saat modul "disiplin" dimatikan — lihat git blame), maka
     * mematikan modul TERTENTU akan membuat variabel itu undefined dan seluruh layout crash
     * untuk SEMUA halaman (karena layout ini dipakai di mana-mana).
     *
     * Test ini mematikan modul satu-per-satu (bergantian, sisanya default aktif) dan pastikan
     * dashboard tetap render (200) utk keempat peran utama — jadi kalau ada modul lain yg
     * kena bug serupa di masa depan, test ini yang akan gagal duluan, bukan user di produksi.
     */
    public function test_mematikan_modul_apapun_satu_per_satu_tidak_membuat_dashboard_crash(): void
    {
        $users = $this->satuUserPerPeran();

        foreach (ModulAktif::kodeValid() as $kode) {
            // Reset semua modul ke default (aktif) tiap iterasi, lalu matikan HANYA yang diuji.
            Setting::whereIn('key', array_map(fn ($k) => ModulAktif::settingKey($k), ModulAktif::kodeValid()))->delete();
            Setting::set(ModulAktif::settingKey($kode), '0');

            foreach ($users as $peran => $user) {
                $status = $this->actingAs($user)->get('/dashboard')->getStatusCode();
                $this->assertNotEquals(
                    500,
                    $status,
                    "Dashboard crash (500) utk peran '{$peran}' saat modul '{$kode}' dimatikan — kemungkinan ada variabel yg didefinisikan di dalam gate modulOn('{$kode}') tapi dipakai di luar gate itu di layouts/app.blade.php."
                );
            }
        }
    }
}
