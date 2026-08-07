<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Models\UjianPelanggaran;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use App\Policies\UjianPolicy;
use App\Support\ModulAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 1 modul Ujian: skema tabel + relasi model + UjianPolicy + toggle modul.
 * Level model/policy langsung (bukan HTTP) krn controller/route baru dibangun Fase 2+.
 */
class UjianFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'ringkasan' => 'MTK', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
    }

    private function buatGuru(string $username): array
    {
        $user = User::create(['username' => $username, 'password' => 'rahasia123', 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $user->uuid, 'nama' => ucfirst($username), 'nik' => (string) random_int(1000000000, 9999999999), 'jk' => 'L']);
        return [$user, $guru];
    }

    private function buatUjian(User $creator): Ujian
    {
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid,
            'created_by'   => $creator->uuid,
            'judul'        => 'PTS Ganjil Matematika',
            'jenis'        => 'pts',
            'target_nilai' => 'pts',
            'durasi_menit' => 90,
        ]);
        UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => UjianKelas::generateToken()]);
        return $ujian;
    }

    public function test_modul_ujian_default_aktif(): void
    {
        $this->assertTrue(ModulAktif::aktif('ujian'));
        $this->assertArrayHasKey('ujian', ModulAktif::semua());
    }

    public function test_skema_dan_relasi_lengkap_bisa_dipakai(): void
    {
        [$user, $guru] = $this->buatGuru('gurupts');
        $ujian = $this->buatUjian($user);
        $ujianKelas = $ujian->kelas()->first();

        $soalMcq = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10, 'urutan' => 1]);
        $opsiBenar = UjianSoalOpsi::create(['id_soal' => $soalMcq->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soalMcq->uuid, 'teks_opsi' => '5', 'is_benar' => false, 'urutan' => 2]);

        $soalMatch = UjianSoal::create([
            'id_ujian' => $ujian->uuid, 'tipe' => 'match', 'teks_soal' => 'Cocokkan',
            'poin' => 10, 'urutan' => 2,
            'meta' => ['pairs' => [['left' => 'Ibukota Riau', 'right' => 'Pekanbaru']]],
        ]);

        $siswaUser = User::create(['username' => 'siswapts', 'password' => 'x', 'access' => 'siswa']);

        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $ujianKelas->uuid,
            'id_siswa'       => $siswaUser->uuid,
            'urutan_soal'    => [$soalMcq->uuid, $soalMatch->uuid],
            'urutan_opsi'    => [$soalMcq->uuid => [$opsiBenar->uuid]],
            'mulai_pada'     => now(),
            'batas_waktu_pada' => now()->addMinutes(90),
        ]);

        $jawaban = UjianJawaban::create([
            'id_attempt' => $attempt->uuid,
            'id_soal'    => $soalMcq->uuid,
            'id_opsi_dipilih' => $opsiBenar->uuid,
        ]);

        UjianPelanggaran::create(['id_attempt' => $attempt->uuid, 'id_siswa' => $siswaUser->uuid, 'tipe' => 'keluar_fullscreen']);

        $this->assertCount(2, $ujian->fresh()->soal);
        $this->assertCount(2, $soalMcq->fresh()->opsi);
        $this->assertSame(['Pekanbaru'], collect($soalMatch->meta['pairs'])->pluck('right')->all());
        $this->assertTrue($attempt->fresh()->siswa->is($siswaUser));
        $this->assertTrue($jawaban->fresh()->selectedOption->is_benar);
        $this->assertCount(1, $attempt->fresh()->pelanggaran);
        // Nilai default kolom DB (mis. status='in_progress') baru tercermin setelah
        // refresh — objek in-memory hasil create() cuma berisi apa yg dikirim eksplisit.
        $attempt->refresh();
        $this->assertSame('in_progress', $attempt->status);
        $this->assertTrue($attempt->isActive());
        $this->assertFalse($attempt->isLocked());
    }

    public function test_policy_manage_untuk_guru_pemilik_ngajar(): void
    {
        [$pemilik, $guruPemilik] = $this->buatGuru('pemilik_pts');
        [$lain, $guruLain] = $this->buatGuru('lain_pts');
        [$pembuat] = $this->buatGuru('pembuat_pts');

        Ngajar::create(['id_guru' => $guruPemilik->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $ujian = $this->buatUjian($pembuat)->load('kelas');
        $policy = new UjianPolicy();

        $this->assertTrue($policy->manage($pemilik, $ujian), 'Guru yg mengajar mapel+kelas ini harus boleh kelola.');
        $this->assertFalse($policy->manage($lain, $ujian), 'Guru yg tidak mengajar mapel+kelas ini tidak boleh kelola.');
        $this->assertTrue($policy->manage($pembuat, $ujian), 'Pembuat ujian selalu boleh kelola, terlepas dari Ngajar.');
    }

    public function test_policy_manage_admin_dan_permission_manage_ujian(): void
    {
        [$pembuat] = $this->buatGuru('pembuat_pts2');
        $ujian = $this->buatUjian($pembuat)->load('kelas');
        $policy = new UjianPolicy();

        $admin = User::create(['username' => 'admin_pts', 'password' => 'x', 'access' => 'admin']);
        $this->assertTrue($policy->manage($admin, $ujian));

        \App\Models\RolePermission::create(['role' => 'kurikulum', 'permission' => 'manage_ujian']);
        $kurikulum = User::create(['username' => 'kur_pts', 'password' => 'x', 'access' => 'kurikulum']);
        $this->assertTrue($policy->manage($kurikulum, $ujian));
    }

    public function test_policy_create_hanya_guru_admin_atau_pemegang_permission(): void
    {
        $policy = new UjianPolicy();
        $guru = User::create(['username' => 'guru_create', 'password' => 'x', 'access' => 'guru']);
        $siswa = User::create(['username' => 'siswa_create', 'password' => 'x', 'access' => 'siswa']);
        $admin = User::create(['username' => 'admin_create', 'password' => 'x', 'access' => 'admin']);

        $this->assertTrue($policy->create($guru));
        $this->assertTrue($policy->create($admin));
        $this->assertFalse($policy->create($siswa));
    }

    public function test_policy_take_hanya_siswa_anggota_kelas_ter_assign_dan_jendela_terbuka(): void
    {
        [$pembuat] = $this->buatGuru('pembuat_take');
        $ujian = $this->buatUjian($pembuat);
        $ujianKelas = $ujian->kelas()->first();

        $siswaAnggota = User::create(['username' => 'siswa_anggota', 'password' => 'x', 'access' => 'siswa']);
        \App\Models\Siswa::create(['id_login' => $siswaAnggota->uuid, 'nama' => 'Siswa Anggota', 'nis' => '1001', 'jk' => 'L', 'id_kelas' => $this->kelas->uuid]);

        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $siswaLain = User::create(['username' => 'siswa_lain_kelas', 'password' => 'x', 'access' => 'siswa']);
        \App\Models\Siswa::create(['id_login' => $siswaLain->uuid, 'nama' => 'Siswa Lain', 'nis' => '1002', 'jk' => 'L', 'id_kelas' => $kelasLain->uuid]);

        $policy = new UjianPolicy();

        // Belum published -> isOpenNow() false.
        $this->assertFalse($policy->take($siswaAnggota, $ujianKelas));

        $ujian->update(['status' => 'published']);
        $ujianKelas->refresh();
        $this->assertTrue($policy->take($siswaAnggota, $ujianKelas));
        $this->assertFalse($policy->take($siswaLain, $ujianKelas), 'Siswa dari kelas yg tidak di-assign tidak boleh ikut.');
    }
}
