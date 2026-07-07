<?php

namespace Tests\Feature;

use App\Models\Pengumuman;
use App\Models\RolePermission;
use App\Models\User;
use App\Notifications\PengumumanBaru;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fitur Pengumuman: riwayat untuk semua user (terfilter peran), CRUD dijaga
 * izin RBAC 'manage_pengumuman', dan penerbitan pengumuman mengirim notifikasi
 * (database + FCM via PengumumanBaru) hanya ke peran sasaran.
 */
class PengumumanTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access, string $username): User
    {
        return User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access'   => $access,
        ]);
    }

    // ─────────────── Akses & izin ───────────────

    public function test_guest_diarahkan_ke_login(): void
    {
        $this->get('/pengumuman')->assertRedirect(route('login'));
    }

    public function test_user_biasa_bisa_lihat_daftar_pengumuman(): void
    {
        $user = $this->makeUser('siswa', 'peng_siswa_list');

        $this->actingAs($user)->get('/pengumuman')->assertOk();
    }

    public function test_user_tanpa_izin_tidak_bisa_buka_form_buat(): void
    {
        $user = $this->makeUser('siswa', 'peng_siswa_forbidden');

        $this->actingAs($user)->get('/pengumuman/buat')->assertForbidden();
    }

    public function test_role_dengan_izin_rbac_bisa_buka_form_buat(): void
    {
        RolePermission::create(['role' => 'kepala', 'permission' => 'manage_pengumuman']);
        $user = $this->makeUser('kepala', 'peng_kepala_create');

        $this->actingAs($user)->get('/pengumuman/buat')->assertOk();
    }

    public function test_admin_selalu_bisa_buka_form_buat(): void
    {
        $admin = $this->makeUser('superadmin', 'peng_admin_create');

        $this->actingAs($admin)->get('/pengumuman/buat')->assertOk();
    }

    // ─────────────── Penerbitan & notifikasi ───────────────

    public function test_terbitkan_pengumuman_kirim_notifikasi_hanya_ke_peran_sasaran(): void
    {
        Notification::fake();

        $admin  = $this->makeUser('superadmin', 'peng_admin_store');
        $guru   = $this->makeUser('guru', 'peng_guru_target');
        $siswa  = $this->makeUser('siswa', 'peng_siswa_nontarget');

        $this->actingAs($admin)->post('/pengumuman', [
            'judul'        => 'Rapat Guru',
            'isi'          => 'Rapat dinas seluruh guru hari Sabtu pukul 09.00.',
            'target_roles' => ['guru'],
        ])->assertRedirect();

        $this->assertDatabaseHas('pengumuman', ['judul' => 'Rapat Guru']);

        Notification::assertSentTo($guru, PengumumanBaru::class);
        Notification::assertNotSentTo($siswa, PengumumanBaru::class);
        Notification::assertNotSentTo($admin, PengumumanBaru::class);
    }

    public function test_target_kosong_kirim_ke_semua_user(): void
    {
        Notification::fake();

        $admin = $this->makeUser('superadmin', 'peng_admin_all');
        $guru  = $this->makeUser('guru', 'peng_guru_all');
        $siswa = $this->makeUser('siswa', 'peng_siswa_all');

        $this->actingAs($admin)->post('/pengumuman', [
            'judul' => 'Libur Nasional',
            'isi'   => 'Sekolah libur memperingati hari besar nasional.',
            // target_roles sengaja tidak dikirim → semua peran
        ])->assertRedirect();

        Notification::assertSentTo($guru, PengumumanBaru::class);
        Notification::assertSentTo($siswa, PengumumanBaru::class);
        Notification::assertSentTo($admin, PengumumanBaru::class);
    }

    public function test_payload_notifikasi_pengumuman_benar(): void
    {
        $pengumuman = Pengumuman::create([
            'judul'        => 'Judul Uji',
            'isi'          => 'Isi pengumuman yang cukup panjang untuk diringkas menjadi pesan notifikasi.',
            'target_roles' => null,
        ]);
        $user = $this->makeUser('guru', 'peng_payload');

        $notif = new PengumumanBaru($pengumuman);

        $this->assertContains('database', $notif->via($user));
        $array = $notif->toArray($user);
        $this->assertSame('pengumuman', $array['type']);
        $this->assertSame($pengumuman->uuid, $array['pengumuman_id']);
        $this->assertSame('Judul Uji', $array['judul']);

        $fcm = $notif->toFcm($user);
        $this->assertSame('pengumuman', $fcm['type']);
        $this->assertSame('notif_sims', $fcm['sound']);
        $this->assertSame('/pengumuman/'.$pengumuman->uuid, $fcm['url']);
    }

    // ─────────────── Show / update / destroy ───────────────

    public function test_user_biasa_tak_bisa_buka_pengumuman_bukan_sasarannya(): void
    {
        $pengumuman = Pengumuman::create([
            'judul'        => 'Khusus Guru',
            'isi'          => 'Rahasia guru.',
            'target_roles' => ['guru'],
        ]);
        $siswa = $this->makeUser('siswa', 'peng_siswa_404');

        $this->actingAs($siswa)->get('/pengumuman/'.$pengumuman->uuid)->assertNotFound();
    }

    public function test_user_sasaran_bisa_buka_pengumuman(): void
    {
        $pengumuman = Pengumuman::create([
            'judul'        => 'Untuk Siswa',
            'isi'          => 'Pesan untuk siswa.',
            'target_roles' => ['siswa'],
        ]);
        $siswa = $this->makeUser('siswa', 'peng_siswa_ok');

        $this->actingAs($siswa)->get('/pengumuman/'.$pengumuman->uuid)->assertOk();
    }

    public function test_edit_tidak_mengirim_ulang_notifikasi(): void
    {
        Notification::fake();

        $admin = $this->makeUser('superadmin', 'peng_admin_edit');
        $this->makeUser('guru', 'peng_guru_edit');

        $pengumuman = Pengumuman::create([
            'judul'        => 'Awal',
            'isi'          => 'Isi awal.',
            'target_roles' => ['guru'],
        ]);

        $this->actingAs($admin)->put('/pengumuman/'.$pengumuman->uuid, [
            'judul'        => 'Diperbarui',
            'isi'          => 'Isi diperbarui.',
            'target_roles' => ['guru'],
        ])->assertRedirect();

        $this->assertDatabaseHas('pengumuman', ['judul' => 'Diperbarui']);
        Notification::assertNothingSent();
    }

    public function test_admin_bisa_hapus_pengumuman(): void
    {
        $admin = $this->makeUser('superadmin', 'peng_admin_del');
        $pengumuman = Pengumuman::create(['judul' => 'Hapus', 'isi' => 'x', 'target_roles' => null]);

        $this->actingAs($admin)->delete('/pengumuman/'.$pengumuman->uuid)->assertRedirect();

        $this->assertDatabaseMissing('pengumuman', ['uuid' => $pengumuman->uuid]);
    }

    // ─────────────── Badge sidebar (unread pengumuman) ───────────────

    public function test_badge_pengumuman_terhitung_lalu_hilang_setelah_dibaca(): void
    {
        $admin = $this->makeUser('superadmin', 'peng_badge_admin');
        $guru  = $this->makeUser('guru', 'peng_badge_guru');

        // Terbitkan tanpa Notification::fake → notifikasi database benar-benar dibuat.
        $this->actingAs($admin)->post('/pengumuman', [
            'judul'        => 'Uji Badge',
            'isi'          => 'Isi uji badge sidebar.',
            'target_roles' => ['guru'],
        ])->assertRedirect();

        $pengumuman = Pengumuman::firstOrFail();

        // Guru punya 1 pengumuman belum dibaca → badge = 1.
        $this->actingAs($guru)->getJson('/notifications-json')
            ->assertOk()->assertJsonPath('unreadPengumuman', 1);

        // Membuka detail menandai notifikasi terkait sebagai sudah dibaca.
        $this->actingAs($guru)->get('/pengumuman/'.$pengumuman->uuid)->assertOk();

        $this->assertSame(0, $guru->fresh()->unreadNotifications()->count());
        $this->actingAs($guru)->getJson('/notifications-json')
            ->assertOk()->assertJsonPath('unreadPengumuman', 0);
    }

    public function test_badge_tidak_terpengaruh_pengumuman_peran_lain(): void
    {
        $admin = $this->makeUser('superadmin', 'peng_badge2_admin');
        $siswa = $this->makeUser('siswa', 'peng_badge2_siswa');

        // Pengumuman khusus guru → siswa tak menerima notifikasi apa pun.
        $this->actingAs($admin)->post('/pengumuman', [
            'judul'        => 'Khusus Guru',
            'isi'          => 'Bukan untuk siswa.',
            'target_roles' => ['guru'],
        ])->assertRedirect();

        $this->actingAs($siswa)->getJson('/notifications-json')
            ->assertOk()->assertJsonPath('unreadPengumuman', 0);
    }

    public function test_validasi_judul_dan_isi_wajib(): void
    {
        $admin = $this->makeUser('superadmin', 'peng_admin_valid');

        $this->actingAs($admin)->post('/pengumuman', ['judul' => '', 'isi' => ''])
            ->assertSessionHasErrors(['judul', 'isi']);
    }
}
