<?php

namespace Tests\Feature;

use App\Integrations\Ludensa\LudensaJenjang;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Ludensa\Livewire\Siswa\DasborSiswa;
use Ludensa\Models\Mapel;
use Ludensa\Models\QuizQuestion;
use Ludensa\Services\AvatarFotoService;
use Tests\TestCase;

class LudensaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'nama_sekolah', 'value' => 'SD Integrasi Test']);
        Setting::create(['key' => 'fitur_ludensa_aktif', 'value' => '1']);
    }

    protected function buatUserSims(string $access, ?string $username = null): User
    {
        return User::create([
            'username' => $username ?? 'ludensa_'.fake()->unique()->userName(),
            'password' => bcrypt('password'),
            'access' => $access,
        ]);
    }

    public function test_siswa_sd_bisa_akses_dasbor_ludensa(): void
    {
        $kelas = Kelas::create(['tingkat' => 2, 'kelas' => 'A']);
        $user = $this->buatUserSims('siswa', 'siswa_ludensa_test');
        Siswa::create([
            'id_login' => $user->uuid,
            'nama' => 'Andi Test',
            'id_kelas' => $kelas->uuid,
        ]);

        $this->actingAs($user)
            ->get(route('ludensa.siswa.dasbor'))
            ->assertOk()
            ->assertSee('Arena', false);
    }

    public function test_jenjang_siswa_diturunkan_dari_kelas(): void
    {
        $kelas2 = Kelas::create(['tingkat' => 2, 'kelas' => 'A']);
        $user2 = $this->buatUserSims('siswa', 'siswa_kelas2_jenjang');
        Siswa::create([
            'id_login' => $user2->uuid,
            'nama' => 'Andi Test',
            'id_kelas' => $kelas2->uuid,
        ]);

        $kelas3 = Kelas::create(['tingkat' => 3, 'kelas' => 'B']);
        $user3 = $this->buatUserSims('siswa', 'siswa_kelas3_test');
        Siswa::create([
            'id_login' => $user3->uuid,
            'nama' => 'Siti Test',
            'id_kelas' => $kelas3->uuid,
        ]);

        $this->assertSame('sd_rendah', $user2->fresh()->jenjang);
        $this->assertSame('sd_kelas_3', $user3->fresh()->jenjang);
        $this->assertTrue(LudensaJenjang::isSd($user3->jenjang));
    }

    public function test_guru_bisa_akses_asisten_ludensa(): void
    {
        $user = $this->buatUserSims('guru', 'guru_ludensa_test');

        $this->actingAs($user)
            ->get(route('ludensa.guru.asisten'))
            ->assertOk()
            ->assertSee('Asisten', false);
    }

    public function test_sims_ludensa_seeder_mengisi_soal(): void
    {
        $this->seed(\Database\Seeders\SimsLudensaSeeder::class);

        $this->assertGreaterThan(0, Mapel::count());
        $this->assertGreaterThan(0, QuizQuestion::withoutGlobalScopes()->count());
    }

    public function test_kelas_2_tidak_melihat_detektif_di_dasbor(): void
    {
        $this->seed(\Database\Seeders\SimsLudensaSeeder::class);

        $kelas = Kelas::create(['tingkat' => 2, 'kelas' => 'A']);
        $user = $this->buatUserSiswaSd($kelas, 'siswa_kelas2_detektif');

        $this->actingAs($user)
            ->get(route('ludensa.siswa.dasbor'))
            ->assertOk()
            ->assertDontSee('Detektif Lumi', false);
    }

    public function test_kelas_3_bisa_akses_detektif(): void
    {
        $this->seed(\Database\Seeders\SimsLudensaSeeder::class);

        $kelas = Kelas::create(['tingkat' => 3, 'kelas' => 'B']);
        $user = $this->buatUserSiswaSd($kelas, 'siswa_kelas3_detektif');

        $this->actingAs($user)
            ->get(route('ludensa.siswa.dasbor'))
            ->assertOk()
            ->assertSee('Detektif Lumi', false);

        $this->actingAs($user)
            ->get(route('ludensa.siswa.detektif'))
            ->assertOk()
            ->assertSee('Misteri Pensil Hilang', false);
    }

    public function test_kelas_2_ditolak_dari_halaman_detektif(): void
    {
        $this->seed(\Database\Seeders\SimsLudensaSeeder::class);

        $kelas = Kelas::create(['tingkat' => 2, 'kelas' => 'C']);
        $user = $this->buatUserSiswaSd($kelas, 'siswa_kelas2_block_detektif');

        $this->actingAs($user)
            ->get(route('ludensa.siswa.detektif'))
            ->assertForbidden();
    }

    public function test_siswa_bisa_unggah_foto_avatar_dan_dikompres(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD diperlukan untuk kompresi foto avatar.');
        }

        Storage::fake('local');
        config(['ludensa.avatar_foto.disk' => 'local']);

        $kelas = Kelas::create(['tingkat' => 2, 'kelas' => 'A']);
        $user = $this->buatUserSiswaSd($kelas, 'siswa_foto_avatar');

        Livewire::actingAs($user)
            ->test(DasborSiswa::class)
            ->set('fotoAvatar', UploadedFile::fake()->image('aku.jpg', 960, 720))
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('foto', $user->avatar_tipe);
        $this->assertNotNull($user->avatar_foto);
        Storage::disk('local')->assertExists($user->avatar_foto);
        $this->assertLessThanOrEqual(307200, Storage::disk('local')->size($user->avatar_foto));
    }

    public function test_pilih_emoji_kembali_setelah_foto_avatar(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD diperlukan untuk kompresi foto avatar.');
        }

        Storage::fake('local');
        config(['ludensa.avatar_foto.disk' => 'local']);

        $kelas = Kelas::create(['tingkat' => 2, 'kelas' => 'B']);
        $user = $this->buatUserSiswaSd($kelas, 'siswa_emoji_avatar');

        Livewire::actingAs($user)
            ->test(DasborSiswa::class)
            ->set('fotoAvatar', UploadedFile::fake()->image('aku.png', 640, 640))
            ->call('pilihAvatar', '🦊')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('emoji', $user->avatar_tipe);
        $this->assertSame('🦊', $user->avatar);
    }

    public function test_avatar_foto_service_crop_dan_kompres(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD diperlukan untuk kompresi foto avatar.');
        }

        Storage::fake('local');
        config(['ludensa.avatar_foto.disk' => 'local']);

        $file = UploadedFile::fake()->image('panorama.jpg', 1200, 800);
        $path = app(AvatarFotoService::class)->simpan($file, 'siswa-test-uuid');

        Storage::disk('local')->assertExists($path);
        $this->assertLessThanOrEqual(307200, Storage::disk('local')->size($path));
        $this->assertStringContainsString('ludensa/avatars/siswa-test-uuid.', $path);
    }

    public function test_avatar_foto_memerlukan_login(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Ekstensi GD diperlukan untuk kompresi foto avatar.');
        }

        Storage::fake('local');
        config(['ludensa.avatar_foto.disk' => 'local']);

        $kelas = Kelas::create(['tingkat' => 2, 'kelas' => 'A']);
        $user = $this->buatUserSiswaSd($kelas, 'siswa_avatar_guest');

        Livewire::actingAs($user)
            ->test(DasborSiswa::class)
            ->set('fotoAvatar', UploadedFile::fake()->image('aku.jpg', 640, 640))
            ->assertHasNoErrors();

        $user->refresh();

        auth()->logout();

        $this->get(route('ludensa.avatar.foto', $user->uuid))
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('ludensa.avatar.foto', $user->uuid))
            ->assertOk()
            ->assertHeader('content-type');
    }

    public function test_modul_ludensa_nonaktif_menolak_akses(): void
    {
        Setting::set('fitur_ludensa_aktif', '0');

        $kelas = Kelas::create(['tingkat' => 2, 'kelas' => 'A']);
        $user = $this->buatUserSiswaSd($kelas, 'siswa_modul_off');

        $this->actingAs($user)
            ->get(route('ludensa.siswa.dasbor'))
            ->assertForbidden();
    }

    public function test_guru_bisa_akses_dasbor_ludensa(): void
    {
        $guru = $this->buatUserSims('guru', 'guru_dasbor_ludensa');

        $this->actingAs($guru)
            ->get(route('ludensa.guru.dasbor'))
            ->assertOk();
    }

    public function test_admin_diarahkan_ke_pengaturan_mekanik(): void
    {
        $admin = $this->buatUserSims('admin', 'admin_ludensa_test');

        $this->actingAs($admin)
            ->get(route('ludensa.beranda'))
            ->assertRedirect(route('ludensa.admin.mekanik'));
    }

    public function test_siswa_smp_melihat_halaman_tidak_tersedia_bukan_403(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $user = $this->buatUserSiswaSd($kelas, 'siswa_smp_ludensa');

        $this->actingAs($user)
            ->get(route('ludensa.beranda'))
            ->assertRedirect(route('ludensa.siswa.tidak-tersedia'));

        $this->actingAs($user)
            ->get(route('ludensa.siswa.tidak-tersedia'))
            ->assertOk()
            ->assertSee('belum tersedia', false);
    }

    protected function buatUserSiswaSd(Kelas $kelas, string $username): User
    {
        $user = $this->buatUserSims('siswa', $username);
        Siswa::create([
            'id_login' => $user->uuid,
            'nama' => 'Siswa Test',
            'id_kelas' => $kelas->uuid,
        ]);

        return $user->fresh();
    }
}
