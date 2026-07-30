<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Support\PasswordSederhana;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Reset password massal + format password sederhana (permintaan sekolah):
 * 6 karakter, huruf kecil + angka saja, tanpa karakter membingungkan (i/l/1/o/0),
 * dan bisa memilih target akun: siswa saja, ortu saja, atau keduanya.
 */
class ResetPasswordMassalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Siswa $siswa;
    private User $userSiswa;
    private User $userOrtu;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'wajah']);

        $this->admin = User::create([
            'username' => 'admin_reset_massal',
            'password' => Hash::make('x'),
            'access' => 'superadmin',
        ]);

        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->userSiswa = User::create([
            'username' => 'siswa_reset', 'password' => Hash::make('lama-siswa'), 'access' => 'siswa',
        ]);
        $this->userOrtu = User::create([
            'username' => 'ortu_reset', 'password' => Hash::make('lama-ortu'), 'access' => 'orangtua',
        ]);
        $this->siswa = Siswa::create([
            'id_login' => $this->userSiswa->uuid,
            'id_kelas' => $kelas->uuid,
            'nama' => 'Siswa Reset Massal',
            'nis' => '778899',
            'jk' => 'L',
            'status' => 'aktif',
        ]);
        Orangtua::create([
            'id_login' => $this->userOrtu->uuid,
            'id_siswa' => $this->siswa->uuid,
            'nama' => 'Ortu Reset Massal',
        ]);
    }

    public function test_password_sederhana_6_karakter_tanpa_karakter_membingungkan(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $p = PasswordSederhana::buat();
            $this->assertSame(6, strlen($p));
            $this->assertMatchesRegularExpression('/^[a-z2-9]{6}$/', $p);
            // i, l, 1, o, 0 dilarang — mirip satu sama lain di banyak font
            $this->assertDoesNotMatchRegularExpression('/[ilo01]/', $p);
        }
    }

    public function test_reset_massal_keduanya_mereset_siswa_dan_ortu(): void
    {
        $this->actingAs($this->admin)
            ->post(route('siswa.reset.bulk'), ['scope' => 'semua', 'target' => 'keduanya'])
            ->assertRedirect(route('siswa.index'));

        $this->userSiswa->refresh();
        $this->userOrtu->refresh();
        $this->assertFalse(Hash::check('lama-siswa', $this->userSiswa->password));
        $this->assertFalse(Hash::check('lama-ortu', $this->userOrtu->password));
        $this->assertTrue((bool) $this->userSiswa->must_change_password);
        $this->assertTrue((bool) $this->userOrtu->must_change_password);

        // Kredensial tersimpan utk diunduh sekali — password baru format sederhana
        $kred = session('reset_kredensial_siswa');
        $this->assertNotEmpty($kred);
        $this->assertMatchesRegularExpression('/^[a-z2-9]{6}$/', $kred[0]['password_siswa']);
        $this->assertMatchesRegularExpression('/^[a-z2-9]{6}$/', $kred[0]['password_ortu']);
    }

    public function test_reset_massal_target_ortu_tidak_menyentuh_akun_siswa(): void
    {
        $this->actingAs($this->admin)
            ->post(route('siswa.reset.bulk'), ['scope' => 'semua', 'target' => 'ortu'])
            ->assertRedirect(route('siswa.index'));

        $this->userSiswa->refresh();
        $this->userOrtu->refresh();
        $this->assertTrue(Hash::check('lama-siswa', $this->userSiswa->password), 'password siswa tidak boleh berubah');
        $this->assertFalse(Hash::check('lama-ortu', $this->userOrtu->password));

        $kred = session('reset_kredensial_siswa');
        $this->assertSame('-', $kred[0]['password_siswa']);
        $this->assertMatchesRegularExpression('/^[a-z2-9]{6}$/', $kred[0]['password_ortu']);
    }

    public function test_reset_massal_target_siswa_tidak_menyentuh_akun_ortu(): void
    {
        $this->actingAs($this->admin)
            ->post(route('siswa.reset.bulk'), ['scope' => 'semua', 'target' => 'siswa'])
            ->assertRedirect(route('siswa.index'));

        $this->userOrtu->refresh();
        $this->assertTrue(Hash::check('lama-ortu', $this->userOrtu->password), 'password ortu tidak boleh berubah');

        $kred = session('reset_kredensial_siswa');
        $this->assertSame('-', $kred[0]['password_ortu']);
    }

    public function test_reset_individual_juga_pakai_password_sederhana(): void
    {
        $this->actingAs($this->admin)->post(route('siswa.reset', $this->siswa->uuid))->assertRedirect();
        $this->assertMatchesRegularExpression('/^[a-z2-9]{6}$/', session('reset_account')['password']);

        $this->actingAs($this->admin)->post(route('siswa.resetOrtu', $this->siswa->uuid))->assertRedirect();
        $this->assertMatchesRegularExpression('/^[a-z2-9]{6}$/', session('reset_account')['password']);
    }

    public function test_halaman_detail_siswa_menampilkan_tombol_ganti_username_ortu(): void
    {
        $this->actingAs($this->admin)->get(route('siswa.show', $this->siswa->uuid))
            ->assertOk()
            ->assertSee('Ganti Username')
            ->assertSee('P.' . $this->siswa->nis);
    }

    public function test_admin_bisa_ganti_username_ortu(): void
    {
        $this->actingAs($this->admin)
            ->post(route('siswa.usernameOrtu', $this->siswa->uuid), ['username' => 'bunda.rahmawati'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->userOrtu->refresh();
        $this->assertSame('bunda.rahmawati', $this->userOrtu->username);
        // Password tidak boleh ikut berubah — ini murni ganti username, bukan reset.
        $this->assertTrue(Hash::check('lama-ortu', $this->userOrtu->password));
    }

    public function test_ganti_username_ortu_ditolak_bila_sudah_dipakai_user_lain(): void
    {
        User::create(['username' => 'sudah.dipakai', 'password' => Hash::make('x'), 'access' => 'orangtua']);

        $this->actingAs($this->admin)
            ->post(route('siswa.usernameOrtu', $this->siswa->uuid), ['username' => 'sudah.dipakai'])
            ->assertSessionHasErrors('username');

        $this->assertSame('ortu_reset', $this->userOrtu->fresh()->username);
    }

    /** Inti alasan fitur ini dibuat: ganti username ortu TIDAK BOLEH mengunci mereka dari
     *  login — P.<NIS> harus tetap bisa dipakai sesudahnya (lihat LoginController). */
    public function test_setelah_ganti_username_ortu_tetap_bisa_login_pakai_p_titik_nis(): void
    {
        $this->actingAs($this->admin)
            ->post(route('siswa.usernameOrtu', $this->siswa->uuid), ['username' => 'bunda.rahmawati']);
        // Lepas sesi admin scr eksplisit — activingAs() menempel di guard yg sama sepanjang
        // proses tes; tanpa logout, guard bisa tetap balikan admin sbg user aktif meski
        // request /login berikutnya sungguhan login sbg akun lain (bukan bug fitur, murni
        // kuirk sesi/guard antar-request dalam satu metode tes).
        $this->post('/logout');

        $this->post('/login', [
            'credential' => 'P.' . $this->siswa->nis,
            'password'   => 'lama-ortu',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->userOrtu->fresh());
    }
}
