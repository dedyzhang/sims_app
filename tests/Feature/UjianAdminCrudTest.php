<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase Ujian (lanjutan): admin/pengelola bisa edit metadata ujian (termasuk pindah
 * mata pelajaran) + hapus ujian draft, dan store() menolak kelas yang tak punya
 * data Ngajar utk mapel yg dipilih ("menyesuaikan dengan data ngajarnya").
 */
class UjianAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
    }

    private function buatGuru(string $username, string $access = 'guru'): array
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => $access]);
        $guru = Guru::create(['id_login' => $user->uuid, 'nama' => ucfirst($username), 'nik' => (string) random_int(1000000000, 9999999999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return [$user, $guru];
    }

    private function buatUjian(User $pembuat, array $idKelas = null): Ujian
    {
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $pembuat->uuid,
            'judul' => 'PTS Ganjil Matematika', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);
        foreach ($idKelas ?? [$this->kelas->uuid] as $id) {
            UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $id, 'token_masuk' => UjianKelas::generateToken()]);
        }
        return $ujian;
    }

    public function test_guru_pengampu_bisa_lihat_dan_ubah_metadata_ujian(): void
    {
        [$user, $guru] = $this->buatGuru('guru_edit1');
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $ujian = $this->buatUjian($user);

        $this->actingAs($user)->get(route('ujian.pengaturan.edit', $ujian))->assertOk();

        $res = $this->actingAs($user)->post(route('ujian.update', $ujian), [
            'judul' => 'PTS Ganjil Matematika (Revisi)', 'jenis' => 'pts', 'durasi_menit' => 60,
            'instruksi' => 'Kerjakan dengan teliti.',
        ]);
        $res->assertRedirect(route('ujian.show', $ujian));

        $ujian->refresh();
        $this->assertSame('PTS Ganjil Matematika (Revisi)', $ujian->judul);
        $this->assertSame(60, $ujian->durasi_menit);
        $this->assertSame('Kerjakan dengan teliti.', $ujian->instruksi);
    }

    public function test_admin_bisa_pindahkan_mata_pelajaran_ujian_draft(): void
    {
        $admin = User::create(['username' => 'admin_edit1', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        [, $guruLain] = $this->buatGuru('guru_pelajaran_baru');
        $pelajaranBaru = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        Ngajar::create(['id_guru' => $guruLain->uuid, 'id_pelajaran' => $pelajaranBaru->uuid, 'id_kelas' => $kelasLain->uuid]);
        $ujian = $this->buatUjian($admin);
        $this->assertSame(1, $ujian->kelas()->count());

        $res = $this->actingAs($admin)->post(route('ujian.update', $ujian), [
            'judul' => $ujian->judul, 'jenis' => 'pts', 'durasi_menit' => 90,
            'id_pelajaran' => $pelajaranBaru->uuid,
        ]);
        $res->assertRedirect();

        $ujian->refresh();
        $this->assertSame($pelajaranBaru->uuid, $ujian->id_pelajaran);
        // Kelas+token lama dilepas krn tak lagi relevan dgn mapel baru (belum tentu diajar mapel itu).
        $this->assertSame(0, $ujian->kelas()->count());
    }

    /**
     * Bug report FL: mengganti "Jenis Ujian" (PTS→PAS) di Pengaturan tidak ikut mengubah
     * "Target Nilai" (ke buku nilai mana skor ditransfer) — dua field ini SENGAJA independen
     * (lihat store()), tapi sebelumnya target_nilai bahkan tak punya form field di Pengaturan
     * sama sekali, jadi tak ada cara memperbaikinya lewat UI selain lewat sini.
     */
    public function test_target_nilai_bisa_diubah_selama_ujian_masih_draft(): void
    {
        $admin = User::create(['username' => 'admin_target_nilai', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $ujian = $this->buatUjian($admin);
        $this->assertSame('pts', $ujian->target_nilai);

        $res = $this->actingAs($admin)->post(route('ujian.update', $ujian), [
            'judul' => $ujian->judul, 'jenis' => 'pas', 'durasi_menit' => 90,
            'target_nilai' => 'pas',
        ]);
        $res->assertRedirect();

        $ujian->refresh();
        $this->assertSame('pas', $ujian->target_nilai);
        $this->assertSame('pas', $ujian->jenis);
    }

    public function test_ubah_target_nilai_ditolak_saat_ujian_sudah_terbit(): void
    {
        $admin = User::create(['username' => 'admin_target_nilai2', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $ujian = $this->buatUjian($admin);
        $ujian->update(['status' => 'published']);

        $this->actingAs($admin)->post(route('ujian.update', $ujian), [
            'judul' => $ujian->judul, 'jenis' => 'pas', 'durasi_menit' => 90,
            'target_nilai' => 'pas',
        ])->assertRedirect();

        // Target nilai TIDAK ikut berubah walau ujian sudah terbit — walau "jenis" (label
        // tampilan) tetap boleh diubah kapan saja (tak divalidasi status draft).
        $this->assertSame('pts', $ujian->fresh()->target_nilai);
    }

    public function test_target_nilai_ujian_sumatif_tak_bisa_diubah(): void
    {
        $admin = User::create(['username' => 'admin_target_nilai3', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $guruUser = User::create(['username' => 'guru_target_nilai3', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Target Nilai', 'nik' => '5551112222', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $ngajar = Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $materi = \App\Models\Materi::create(['id_ngajar' => $ngajar->uuid, 'nama' => 'Bab 1']);
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'id_materi' => $materi->uuid, 'created_by' => $admin->uuid,
            'judul' => 'Ulangan Harian Bab 1', 'jenis' => 'harian', 'target_nilai' => 'sumatif', 'durasi_menit' => 45,
        ]);

        $this->actingAs($admin)->post(route('ujian.update', $ujian), [
            'judul' => $ujian->judul, 'jenis' => 'harian', 'durasi_menit' => 45,
            'target_nilai' => 'pas',
        ])->assertRedirect();

        $this->assertSame('sumatif', $ujian->fresh()->target_nilai);
    }

    public function test_ubah_pelajaran_ditolak_saat_ujian_sudah_terbit(): void
    {
        $admin = User::create(['username' => 'admin_edit2', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $pelajaranLain = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
        $ujian = $this->buatUjian($admin);
        $ujian->update(['status' => 'published']);

        $this->actingAs($admin)->post(route('ujian.update', $ujian), [
            'judul' => $ujian->judul, 'jenis' => 'pts', 'durasi_menit' => 90,
            'id_pelajaran' => $pelajaranLain->uuid,
        ])->assertRedirect();

        // Field lain tetap tersimpan, tapi pelajaran (dan kelasnya) tidak ikut berubah.
        $ujian->refresh();
        $this->assertSame($this->pelajaran->uuid, $ujian->id_pelajaran);
        $this->assertSame(1, $ujian->kelas()->count());
    }

    public function test_update_ditolak_setelah_ujian_ditutup(): void
    {
        $admin = User::create(['username' => 'admin_edit3', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $ujian = $this->buatUjian($admin);
        $ujian->update(['status' => 'closed']);

        $this->actingAs($admin)->post(route('ujian.update', $ujian), [
            'judul' => 'Coba Ubah', 'jenis' => 'pts', 'durasi_menit' => 90,
        ])->assertStatus(422);

        $this->assertSame('PTS Ganjil Matematika', $ujian->fresh()->judul);
    }

    public function test_guru_lain_tak_bisa_akses_pengaturan_atau_update(): void
    {
        [$user] = $this->buatGuru('guru_edit_bukan_pemilik'); // tanpa Ngajar
        $pembuat = User::create(['username' => 'guru_edit_pemilik', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $ujian = $this->buatUjian($pembuat);

        $this->actingAs($user)->get(route('ujian.pengaturan.edit', $ujian))->assertForbidden();
        $this->actingAs($user)->post(route('ujian.update', $ujian), [
            'judul' => 'Coba Ubah', 'jenis' => 'pts', 'durasi_menit' => 90,
        ])->assertForbidden();
    }

    public function test_admin_bisa_hapus_ujian_draft(): void
    {
        $admin = User::create(['username' => 'admin_hapus1', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $ujian = $this->buatUjian($admin);

        $this->actingAs($admin)->delete(route('ujian.destroy', $ujian))->assertRedirect(route('ujian.index'));
        $this->assertSoftDeleted('ujians', ['uuid' => $ujian->uuid]);
    }

    public function test_hapus_ditolak_selama_ujian_masih_terbit(): void
    {
        $admin = User::create(['username' => 'admin_hapus2', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $ujian = $this->buatUjian($admin);
        $ujian->update(['status' => 'published']);

        $this->actingAs($admin)->delete(route('ujian.destroy', $ujian))->assertStatus(422);
        $this->assertDatabaseHas('ujians', ['uuid' => $ujian->uuid, 'deleted_at' => null]);
    }

    public function test_guru_lain_tak_bisa_hapus_ujian_orang(): void
    {
        [$user] = $this->buatGuru('guru_hapus_bukan_pemilik'); // tanpa Ngajar
        $pembuat = User::create(['username' => 'guru_hapus_pemilik', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $ujian = $this->buatUjian($pembuat);

        $this->actingAs($user)->delete(route('ujian.destroy', $ujian))->assertForbidden();
        $this->assertDatabaseHas('ujians', ['uuid' => $ujian->uuid, 'deleted_at' => null]);
    }

    public function test_store_menolak_kelas_yang_tak_punya_data_ngajar_utk_mapel_ini(): void
    {
        // Penetapan kelas saat store() eksklusif admin/pengelola (guru pengampu tak lagi
        // mengirim/menetapkan kelas sendiri) — dites lewat admin.
        $admin = User::create(['username' => 'admin_kelas_invalid', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        [, $guru] = $this->buatGuru('guru_kelas_invalid');
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        // Kelas ini valid sbg data (ada di tabel kelas) tapi TAK ADA Ngajar utk mapel ini.
        $kelasTanpaNgajar = Kelas::create(['tingkat' => 8, 'kelas' => 'C']);

        $this->actingAs($admin)->post(route('ujian.store'), [
            'judul' => 'PTS Nekat Kelas', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => [$kelasTanpaNgajar->uuid], 'durasi_menit' => 90,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('ujians', ['judul' => 'PTS Nekat Kelas']);
    }

    public function test_store_hanya_menetapkan_kelas_valid_dan_mengabaikan_yang_tidak(): void
    {
        $admin = User::create(['username' => 'admin_kelas_campur', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        [, $guru] = $this->buatGuru('guru_kelas_campur');
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $kelasTanpaNgajar = Kelas::create(['tingkat' => 8, 'kelas' => 'C']);

        $res = $this->actingAs($admin)->post(route('ujian.store'), [
            'judul' => 'PTS Kelas Campur', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas' => [$this->kelas->uuid, $kelasTanpaNgajar->uuid],
            'durasi_menit' => 90,
        ]);
        $res->assertRedirect();

        $ujian = Ujian::where('judul', 'PTS Kelas Campur')->firstOrFail();
        $this->assertSame(1, $ujian->kelas()->count());
        $this->assertDatabaseHas('ujian_kelas', ['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid]);
        $this->assertDatabaseMissing('ujian_kelas', ['id_ujian' => $ujian->uuid, 'id_kelas' => $kelasTanpaNgajar->uuid]);
    }

    public function test_guru_tak_bisa_atur_kelas_via_sync(): void
    {
        [$user, $guru] = $this->buatGuru('guru_sync_ditolak');
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $ujian = $this->buatUjian($user, []);

        $this->actingAs($user)->post(route('ujian.kelas.sync', $ujian), [
            'id_kelas' => [$this->kelas->uuid],
        ])->assertForbidden();

        $this->assertSame(0, $ujian->kelas()->count());
    }

    public function test_halaman_kelas_guru_tak_punya_form_atur_kelas(): void
    {
        [$user, $guru] = $this->buatGuru('guru_show_readonly');
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $ujian = $this->buatUjian($user);

        $html = $this->actingAs($user)->get(route('ujian.show', $ujian))->assertOk()->getContent();
        $this->assertStringNotContainsString('Simpan Kelas', $html, 'Guru tak boleh lihat form atur kelas — cuma admin/pengelola.');

        $admin = User::create(['username' => 'admin_show_editable', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $htmlAdmin = $this->actingAs($admin)->get(route('ujian.show', $ujian))->assertOk()->getContent();
        $this->assertStringContainsString('Simpan Kelas', $htmlAdmin);
    }

    public function test_index_ujian_punya_aksi_cepat_reset_token_dan_tutup_utk_ujian_terbit(): void
    {
        [$user, $guru] = $this->buatGuru('guru_index_aksi');
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $ujianDraf = $this->buatUjian($user);
        $ujianTerbit = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $user->uuid,
            'judul' => 'PTS Terbit Aksi', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90, 'status' => 'published',
        ]);
        UjianKelas::create(['id_ujian' => $ujianTerbit->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => UjianKelas::generateToken()]);

        $html = $this->actingAs($user)->get(route('ujian.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Reset Token', $html);
        $this->assertStringContainsString('Tutup', $html);

        // Reset token & tutup langsung dari /ujian (tanpa buka detail) — masih boleh guru.
        $tokenLama = $ujianTerbit->kelas()->value('token_masuk');
        $this->actingAs($user)->post(route('ujian.token.reset', $ujianTerbit))->assertRedirect();
        $this->assertNotSame($tokenLama, $ujianTerbit->kelas()->first()->fresh()->token_masuk);

        $this->actingAs($user)->post(route('ujian.close', $ujianTerbit))->assertRedirect();
        $this->assertSame('closed', $ujianTerbit->fresh()->status);
    }
}
