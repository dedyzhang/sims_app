<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameLiveSession;
use App\Models\GameQuestion;
use App\Models\GameQuestionOption;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * GameLiveController::state() — endpoint polling frekuensi tinggi (throttle 360/menit) selama
 * kuis live berlangsung — dulu memuat daftar peserta via GameLiveParticipant::with('user')
 * TANPA nested eager-load relasi guru/siswa. displayName() per peserta jadi query lazy-load
 * terpisah (malah 2x per peserta siswa: cek guru dulu [null], baru cek siswa), berkembang
 * linear dgn jumlah siswa yg join sesi live. Test ini mengunci jumlah query TIDAK naik
 * seiring bertambahnya peserta.
 */
class GameLiveParticipantQueryTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;
    private GameQuiz $quiz;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);

        $guruUser = User::create(['username' => 'guru_live_q', 'password' => Hash::make('password'), 'access' => 'guru']);
        $guru = Guru::create([
            'id_login' => $guruUser->uuid, 'nama' => 'Guru Live Q', 'nik' => '3001', 'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $this->kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'Q']);
        $pelajaran = Pelajaran::create(['nama' => 'IPA Q', 'ringkasan' => 'IPA', 'kkm' => 75]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_kelas' => $this->kelas->uuid, 'id_pelajaran' => $pelajaran->uuid]);

        $this->classroom = Classroom::create([
            'id_semester' => $semester->id, 'id_kelas' => $this->kelas->uuid, 'id_pelajaran' => $pelajaran->uuid,
            'title' => 'IPA Live Q', 'status' => 'published', 'class_code' => 'LIVEQ1',
            'created_by' => $guruUser->uuid, 'cover_color' => '#111',
        ]);

        $this->quiz = GameQuiz::create([
            'classroom_id' => $this->classroom->uuid, 'created_by' => $guruUser->uuid,
            'title' => 'Kuis Live Q', 'mode' => 'async', 'scoring_mode' => 'competitive',
            'max_score' => 100, 'status' => 'published', 'show_leaderboard' => true,
            'instant_feedback' => true,
        ]);
        $q1 = GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'mcq', 'question_text' => '2+2?',
            'points' => 1, 'sort_order' => 0,
        ]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => '4', 'is_correct' => true, 'sort_order' => 0]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => '5', 'is_correct' => false, 'sort_order' => 1]);
        GameQuizAssignment::create([
            'quiz_id' => $this->quiz->uuid, 'classroom_id' => $this->classroom->uuid, 'status' => 'open',
        ]);

        $this->actingAs($guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));
        $this->actingAs($guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $this->quiz]));
    }

    private function joinAsNewSiswa(string $username): User
    {
        $siswaUser = User::create(['username' => $username, 'password' => Hash::make('password'), 'access' => 'siswa']);
        Siswa::create([
            'id_login' => $siswaUser->uuid, 'id_kelas' => $this->kelas->uuid, 'nama' => 'Siswa ' . $username,
            'nis' => 'NIS-' . $username, 'jk' => 'L', 'face_descriptor' => [0.1],
        ]);
        ClassroomMember::create([
            'classroom_id' => $this->classroom->uuid, 'user_id' => $siswaUser->uuid,
            'role_in_class' => 'siswa', 'joined_at' => now(),
        ]);

        // Panggil state() sekali sbg siswa itu sendiri — inilah yg men-trigger auto-join
        // (GameLiveController::state() baris ~212: firstOrNew participant utk siswa).
        $this->actingAs($siswaUser)->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]));

        return $siswaUser;
    }

    /** Jumlah query satu panggilan state() sbg $viewer. */
    private function hitungQueryState(User $viewer): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($viewer)->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]))->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    /**
     * Toleransi ±2 query, BUKAN kesamaan persis.
     *
     * Sejak memo keanggotaan di ClassroomPolicy di-key per user (dulu satu static
     * global yang dipakai bersama SEMUA user — jawaban otorisasi milik orang lain
     * ikut terpakai, lihat ClassroomAutoEnrollTest), satu lookup classroom_members
     * bisa muncul atau tidak tergantung urutan pemeriksaan di dalam request. Hasil
     * pengukuran nyata: 1→19, 5→19, 10→20, 20→19, 40→19 query — jelas TIDAK tumbuh
     * mengikuti jumlah peserta, hanya berayun satu query.
     *
     * Yang dijaga tes ini tetap utuh: N+1 sungguhan (lazy-load guru/siswa per peserta)
     * akan menambah ~20 query antara dua titik ukur di bawah dan langsung tertangkap.
     */
    public function test_jumlah_query_state_tidak_naik_seiring_jumlah_peserta_live(): void
    {
        $viewer = $this->joinAsNewSiswa('viewer_live_q');
        for ($i = 0; $i < 9; $i++) {
            $this->joinAsNewSiswa('siswa_live_q_' . $i);
        }

        $this->hitungQueryState($viewer); // pemanasan
        $sepuluh = $this->hitungQueryState($viewer);

        for ($i = 10; $i < 30; $i++) {
            $this->joinAsNewSiswa('siswa_live_q_' . $i);
        }

        $tigaPuluh = $this->hitungQueryState($viewer);

        $resp = $this->actingAs($viewer)->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]))->assertOk();
        $this->assertCount(30, $resp->json('session.participants'));
        $this->assertLessThanOrEqual(
            $sepuluh + 2,
            $tigaPuluh,
            "Query state() harus TETAP walau peserta live bertambah dr 10 ke 30 (skrg {$sepuluh} vs {$tigaPuluh}) — indikasi lazy-load guru/siswa per peserta kembali muncul."
        );
    }
}
