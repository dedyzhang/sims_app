<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameAttempt;
use App\Models\GameFocusEvent;
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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ArenaFocusLockTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $siswa;
    private Classroom $classroom;
    private GameQuiz $quiz;
    private GameAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);

        $this->guru = User::create(['username' => 'guru_focus', 'password' => Hash::make('x'), 'access' => 'guru']);
        $guru = Guru::create([
            'id_login' => $this->guru->uuid, 'nama' => 'Guru Fokus', 'nik' => '7701', 'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);
        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'MTK', 'ringkasan' => 'MTK', 'kkm' => 75]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_kelas' => $kelas->uuid, 'id_pelajaran' => $pelajaran->uuid]);

        $this->classroom = Classroom::create([
            'id_semester' => $semester->id, 'id_kelas' => $kelas->uuid, 'id_pelajaran' => $pelajaran->uuid,
            'title' => 'MTK 8A', 'status' => 'published', 'class_code' => 'FOC01',
            'created_by' => $this->guru->uuid,
        ]);

        $this->siswa = User::create(['username' => 'siswa_focus', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create([
            'id_login' => $this->siswa->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Fokus',
            'nis' => '8801', 'jk' => 'L', 'face_descriptor' => [0.1],
        ]);
        ClassroomMember::create([
            'classroom_id' => $this->classroom->uuid, 'user_id' => $this->siswa->uuid,
            'role_in_class' => 'siswa', 'joined_at' => now(),
        ]);

        $this->quiz = GameQuiz::create([
            'classroom_id' => $this->classroom->uuid, 'created_by' => $this->guru->uuid,
            'title' => 'Kuis Fokus', 'mode' => 'async', 'scoring_mode' => 'accuracy',
            'max_score' => 100, 'status' => 'published', 'is_locked' => true, 'access_token' => 'ABCD',
        ]);
        $q = GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'mcq', 'question_text' => '1+1?',
            'points' => 1, 'sort_order' => 0,
        ]);
        GameQuestionOption::create(['question_id' => $q->uuid, 'option_text' => '2', 'is_correct' => true, 'sort_order' => 0]);
        GameQuestionOption::create(['question_id' => $q->uuid, 'option_text' => '3', 'is_correct' => false, 'sort_order' => 1]);

        $assignment = GameQuizAssignment::create([
            'quiz_id' => $this->quiz->uuid, 'classroom_id' => $this->classroom->uuid, 'status' => 'open',
        ]);
        $this->attempt = GameAttempt::create([
            'assignment_id' => $assignment->uuid,
            'student_id' => $this->siswa->uuid,
            'source' => GameAttempt::SOURCE_ASYNC,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function test_siswa_can_log_focus_exit(): void
    {
        $this->actingAs($this->siswa)
            ->postJson(route('classroom.arena.focus-exit', [$this->classroom, $this->quiz]), [
                'context' => 'solo',
                'reason' => 'pindah tab/aplikasi',
                'attempt_id' => $this->attempt->uuid,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('game_focus_events', [
            'quiz_id' => $this->quiz->uuid,
            'classroom_id' => $this->classroom->uuid,
            'student_id' => $this->siswa->uuid,
            'context' => 'solo',
            'type' => 'keluar',
            'attempt_id' => $this->attempt->uuid,
            'reason' => 'pindah tab/aplikasi',
        ]);
    }

    public function test_focus_exit_strips_attempt_from_other_quiz(): void
    {
        $otherQuiz = GameQuiz::create([
            'classroom_id' => $this->classroom->uuid, 'created_by' => $this->guru->uuid,
            'title' => 'Kuis Lain', 'mode' => 'async', 'scoring_mode' => 'accuracy',
            'max_score' => 100, 'status' => 'published', 'is_locked' => true, 'access_token' => 'ZZZZ',
        ]);
        $otherAssignment = GameQuizAssignment::create([
            'quiz_id' => $otherQuiz->uuid, 'classroom_id' => $this->classroom->uuid, 'status' => 'open',
        ]);
        $foreignAttempt = GameAttempt::create([
            'assignment_id' => $otherAssignment->uuid,
            'student_id' => $this->siswa->uuid,
            'source' => GameAttempt::SOURCE_ASYNC,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $this->actingAs($this->siswa)
            ->postJson(route('classroom.arena.focus-exit', [$this->classroom, $this->quiz]), [
                'context' => 'solo',
                'reason' => 'tes',
                'attempt_id' => $foreignAttempt->uuid,
            ])
            ->assertOk();

        $this->assertNull(
            GameFocusEvent::where('quiz_id', $this->quiz->uuid)
                ->where('student_id', $this->siswa->uuid)
                ->value('attempt_id')
        );
    }

    public function test_answer_without_solo_unlock_is_forbidden(): void
    {
        $q = $this->quiz->questions()->first();
        $opt = $q->options->first();

        $this->actingAs($this->siswa)
            ->postJson(route('classroom.arena.answer', [$this->classroom, $this->quiz, $this->attempt]), [
                'question_id' => $q->uuid,
                'selected_option_id' => $opt->uuid,
            ])
            ->assertForbidden();
    }

    public function test_play_page_shows_focus_gate_for_siswa(): void
    {
        $this->actingAs($this->siswa)
            ->withSession(['arena_solo_unlock.'.$this->quiz->uuid => 'ABCD'])
            ->get(route('classroom.arena.play', [$this->classroom, $this->quiz, $this->attempt]))
            ->assertOk()
            ->assertSee('Mode Fokus Arena', false)
            ->assertSee('Mulai layar penuh', false);
    }

    public function test_results_shows_focus_exit_count(): void
    {
        GameFocusEvent::create([
            'quiz_id' => $this->quiz->uuid,
            'classroom_id' => $this->classroom->uuid,
            'student_id' => $this->siswa->uuid,
            'context' => 'solo',
            'attempt_id' => $this->attempt->uuid,
            'type' => 'keluar',
            'reason' => 'keluar layar penuh',
        ]);
        GameFocusEvent::create([
            'quiz_id' => $this->quiz->uuid,
            'classroom_id' => $this->classroom->uuid,
            'student_id' => $this->siswa->uuid,
            'context' => 'solo',
            'attempt_id' => $this->attempt->uuid,
            'type' => 'keluar',
            'reason' => 'pindah tab/aplikasi',
        ]);

        $this->actingAs($this->guru)
            ->get(route('classroom.arena.results', [$this->classroom, $this->quiz]))
            ->assertOk()
            ->assertSee('Keluar fokus', false)
            ->assertSee('2×', false);
    }
}
