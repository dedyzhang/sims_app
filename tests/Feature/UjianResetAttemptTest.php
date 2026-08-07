<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 5 modul Ujian: dashboard pemantauan live guru — buka-kunci (lanjut) vs
 * reset-ulang (soft-cancel, mulai dari nol dgn token sama), keduanya lewat
 * UjianMonitorController & dilindungi UjianPolicy.
 */
class UjianResetAttemptTest extends TestCase
{
    use RefreshDatabase;

    private Ujian $ujian;
    private UjianKelas $ujianKelas;
    private User $guruUser;
    private User $siswaUser;
    private UjianAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        $this->guruUser = User::create(['username' => 'guru_monitor', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $this->guruUser->uuid, 'nama' => 'Guru Monitor', 'nik' => '7777777777', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => 'PTS Monitor', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
        $soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '?', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);
        $this->ujianKelas = UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $kelas->uuid, 'token_masuk' => 'MONITORTOKEN']);

        $this->siswaUser = User::create(['username' => 'siswa_monitor', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $this->siswaUser->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Monitor', 'nis' => '7001', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'MONITORTOKEN'])->assertRedirect();
        $this->attempt = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail();
    }

    public function test_buka_kunci_membuka_dikunci_dan_mencatat_pelanggaran_reset(): void
    {
        $this->attempt->update(['dikunci' => true]);

        $this->actingAs($this->guruUser)->post(route('ujian.monitor.unlock', [$this->ujian, $this->attempt]))->assertRedirect();

        $this->attempt->refresh();
        $this->assertFalse($this->attempt->dikunci);
        $this->assertSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->status, 'Buka-kunci TIDAK membatalkan attempt, cuma buka kuncinya.');
        $this->assertDatabaseHas('ujian_pelanggaran', ['id_attempt' => $this->attempt->uuid, 'tipe' => 'reset_oleh_guru']);
    }

    public function test_reset_ulang_soft_cancel_bukan_hard_delete(): void
    {
        $this->actingAs($this->guruUser)->post(route('ujian.monitor.resetAttempt', [$this->ujian, $this->attempt]))->assertRedirect();

        $this->attempt->refresh();
        $this->assertSame(UjianAttempt::STATUS_DIBATALKAN, $this->attempt->status);
        $this->assertFalse($this->attempt->dikunci);
        // Baris lama harus tetap ada utk audit, bukan dihapus.
        $this->assertDatabaseHas('ujian_attempts', ['uuid' => $this->attempt->uuid]);
        $this->assertDatabaseHas('ujian_pelanggaran', ['id_attempt' => $this->attempt->uuid, 'tipe' => 'direset_admin']);
    }

    public function test_siswa_bisa_mulai_lagi_dengan_token_sama_setelah_direset(): void
    {
        $this->actingAs($this->guruUser)->post(route('ujian.monitor.resetAttempt', [$this->ujian, $this->attempt]))->assertRedirect();

        $this->actingAs($this->siswaUser)
            ->post(route('ujian.siswa.start', $this->ujian), ['token' => 'MONITORTOKEN'])
            ->assertRedirect();

        $this->assertSame(2, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->count(), 'Attempt lama (dibatalkan) + attempt baru (in_progress).');
        $baru = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->where('status', UjianAttempt::STATUS_IN_PROGRESS)->firstOrFail();
        $this->assertNotSame($this->attempt->uuid, $baru->uuid);
    }

    public function test_guru_lain_tak_bisa_reset_attempt_ujian_orang(): void
    {
        $guruLain = User::create(['username' => 'guru_monitor_lain', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $guruLain->uuid, 'nama' => 'Guru Lain', 'nik' => '8888888888', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($guruLain)->get(route('ujian.monitor.index', $this->ujian))->assertForbidden();
        $this->actingAs($guruLain)->post(route('ujian.monitor.resetAttempt', [$this->ujian, $this->attempt]))->assertForbidden();
        $this->assertSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->fresh()->status);
    }

    public function test_poll_mengembalikan_data_json_attempt_ujian_ini(): void
    {
        $res = $this->actingAs($this->guruUser)->getJson(route('ujian.monitor.poll', $this->ujian));
        $res->assertOk();
        $res->assertJsonPath('attempts.0.nama', 'Siswa Monitor');
        $res->assertJsonPath('attempts.0.status', 'in_progress');
    }

    public function test_reset_ulang_dua_kali_ditolak_pada_percobaan_kedua(): void
    {
        $this->actingAs($this->guruUser)->post(route('ujian.monitor.resetAttempt', [$this->ujian, $this->attempt]))->assertRedirect();
        $this->actingAs($this->guruUser)->post(route('ujian.monitor.resetAttempt', [$this->ujian, $this->attempt]))->assertStatus(422);
    }
}
