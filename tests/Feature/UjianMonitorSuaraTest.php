<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianKelas;
use App\Models\UjianPelanggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UjianMonitorSuaraTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private Kelas $kelas;
    private Pelajaran $pelajaran;
    private Semester $semester;
    private Ujian $ujian;
    private UjianKelas $ujianKelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guru = User::create([
            'username' => 'guru_monitor_suara',
            'password' => Hash::make('password'),
            'access'   => 'guru',
        ]);

        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->pelajaran = Pelajaran::create(['nama' => 'Informatika']);
        $this->semester = Semester::create(['semester' => 1, 'tahun' => '2026/2027', 'aktif' => true]);

        $this->ujian = Ujian::create([
            'judul'        => 'UH Informatika Bab 1',
            'id_pelajaran' => $this->pelajaran->uuid,
            'created_by'   => $this->guru->uuid,
            'id_semester'  => $this->semester->id,
            'jenis'        => 'harian',
            'durasi_menit' => 60,
            'status'       => 'published',
            'mode_skor'    => 'otomatis',
        ]);

        $this->ujianKelas = UjianKelas::create([
            'id_ujian'   => $this->ujian->uuid,
            'id_kelas'   => $this->kelas->uuid,
            'token_masuk' => 'INF001',
        ]);
    }

    public function test_poll_mengembalikan_status_dikunci_dan_alasan_pelanggaran(): void
    {
        $siswaUser = User::create([
            'username' => 'siswa_pelanggar',
            'password' => Hash::make('password'),
            'access'   => 'siswa',
        ]);
        Siswa::create([
            'nama'     => 'Budi Siswa',
            'nis'      => '12345',
            'id_kelas' => $this->kelas->uuid,
            'id_login' => $siswaUser->uuid,
        ]);

        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid,
            'id_siswa'       => $siswaUser->uuid,
            'status'         => UjianAttempt::STATUS_IN_PROGRESS,
            'dikunci'        => true,
            'urutan_soal'    => json_encode(['dummy-soal-1']),
            'mulai_pada'     => now(),
            'batas_waktu_pada' => now()->addMinutes(60),
        ]);

        UjianPelanggaran::create([
            'id_attempt' => $attempt->uuid,
            'id_siswa'   => $siswaUser->uuid,
            'tipe'       => 'keluar_fullscreen',
        ]);

        $res = $this->actingAs($this->guru)->getJson(route('ujian.monitor.poll', $this->ujian));

        $res->assertOk();
        $res->assertJsonFragment([
            'nama'               => 'Budi Siswa',
            'dikunci'            => true,
            'pelanggaran'        => 1,
            'alasan_pelanggaran' => 'Keluar Fullscreen',
        ]);
    }

    public function test_poll_dengan_kelas_semua_mengembalikan_roster_lintas_kelas(): void
    {
        $kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        UjianKelas::create([
            'id_ujian'    => $this->ujian->uuid,
            'id_kelas'    => $kelasB->uuid,
            'token_masuk' => 'INF002',
        ]);

        $siswaUserA = User::create(['username' => 'siswa_a', 'password' => Hash::make('password'), 'access' => 'siswa']);
        Siswa::create(['nama' => 'Siswa Kelas A', 'nis' => '101', 'id_kelas' => $this->kelas->uuid, 'id_login' => $siswaUserA->uuid]);

        $siswaUserB = User::create(['username' => 'siswa_b', 'password' => Hash::make('password'), 'access' => 'siswa']);
        Siswa::create(['nama' => 'Siswa Kelas B', 'nis' => '102', 'id_kelas' => $kelasB->uuid, 'id_login' => $siswaUserB->uuid]);

        $res = $this->actingAs($this->guru)->getJson(route('ujian.monitor.poll', ['ujian' => $this->ujian, 'kelas' => 'semua']));

        $res->assertOk();
        $attempts = collect($res->json('attempts'));

        $this->assertCount(2, $attempts);
        $this->assertTrue($attempts->contains('nama', 'Siswa Kelas A'));
        $this->assertTrue($attempts->contains('nama', 'Siswa Kelas B'));
    }

    public function test_ruangan_poll_mengembalikan_alasan_pelanggaran(): void
    {
        $paket = \App\Models\UjianPaket::create([
            'nama'        => 'PTS Semester Ganjil',
            'id_semester' => $this->semester->id,
            'created_by'  => $this->guru->uuid,
            'status'      => 'published',
        ]);
        $this->ujian->update(['id_ujian_paket' => $paket->uuid]);

        $ruangan = \App\Models\UjianRuangan::create([
            'id_ujian_paket' => $paket->uuid,
            'nama'           => 'Ruang 01',
            'kapasitas'      => 30,
        ]);

        $siswaUser = User::create([
            'username' => 'siswa_ruang_pelanggar',
            'password' => Hash::make('password'),
            'access'   => 'siswa',
        ]);
        $siswa = Siswa::create([
            'nama'     => 'Citra Siswa',
            'nis'      => '54321',
            'id_kelas' => $this->kelas->uuid,
            'id_login' => $siswaUser->uuid,
        ]);

        \App\Models\UjianRuanganPeserta::create([
            'id_ruangan' => $ruangan->uuid,
            'id_siswa'   => $siswa->uuid,
        ]);

        // Tambah jadwal hari ini agar ruangan monitor aktif
        \App\Models\UjianJadwal::create([
            'id_ujian_paket' => $paket->uuid,
            'id_ujian'       => $this->ujian->uuid,
            'id_kelas'       => $this->kelas->uuid,
            'tanggal'        => now()->toDateString(),
            'jam_mulai'      => '07:00',
            'jam_selesai'    => '12:00',
        ]);

        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid,
            'id_siswa'       => $siswaUser->uuid,
            'status'         => UjianAttempt::STATUS_IN_PROGRESS,
            'dikunci'        => true,
            'urutan_soal'    => json_encode(['dummy-1']),
            'mulai_pada'     => now(),
            'batas_waktu_pada' => now()->addMinutes(60),
        ]);

        UjianPelanggaran::create([
            'id_attempt' => $attempt->uuid,
            'id_siswa'   => $siswaUser->uuid,
            'tipe'       => 'ganti_tab',
        ]);

        $res = $this->actingAs($this->guru)->getJson(route('ujian.ruangan.poll', $ruangan));

        $res->assertOk();
        $res->assertJsonFragment([
            'nama'               => 'Citra Siswa',
            'dikunci'            => true,
            'pelanggaran'        => 1,
            'alasan_pelanggaran' => 'Berpindah Tab',
        ]);
    }
}
