<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameAnswer;
use App\Models\GameAttempt;
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
use App\Notifications\ArenaLiveStartedNotification;
use App\Services\GameAnswerGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GameLiveTest extends TestCase
{
    use RefreshDatabase;

    protected User $guruUser;
    protected User $siswaUser;
    protected User $otherSiswa;
    protected Classroom $classroom;
    protected GameQuiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);

        $this->guruUser = User::create(['username' => 'guru_live', 'password' => Hash::make('password'), 'access' => 'guru']);
        $guru = Guru::create([
            'id_login' => $this->guruUser->uuid, 'nama' => 'Guru Live', 'nik' => '2001', 'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'IPA', 'ringkasan' => 'IPA', 'kkm' => 75]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_kelas' => $kelas->uuid, 'id_pelajaran' => $pelajaran->uuid]);

        $this->classroom = Classroom::create([
            'id_semester' => $semester->id, 'id_kelas' => $kelas->uuid, 'id_pelajaran' => $pelajaran->uuid,
            'title' => 'IPA 8A', 'status' => 'published', 'class_code' => 'LIVE01',
            'created_by' => $this->guruUser->uuid, 'cover_color' => '#111',
        ]);

        $this->siswaUser = User::create(['username' => 'siswa_live', 'password' => Hash::make('password'), 'access' => 'siswa']);
        Siswa::create([
            'id_login' => $this->siswaUser->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Live',
            'nis' => '8001', 'jk' => 'L', 'face_descriptor' => [0.1],
        ]);
        ClassroomMember::create([
            'classroom_id' => $this->classroom->uuid, 'user_id' => $this->siswaUser->uuid,
            'role_in_class' => 'siswa', 'joined_at' => now(),
        ]);

        $this->otherSiswa = User::create(['username' => 'siswa_luar_live', 'password' => Hash::make('password'), 'access' => 'siswa']);
        $kelasB = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);
        Siswa::create([
            'id_login' => $this->otherSiswa->uuid, 'id_kelas' => $kelasB->uuid, 'nama' => 'Luar',
            'nis' => '8002', 'jk' => 'P', 'face_descriptor' => [0.1],
        ]);

        $this->quiz = GameQuiz::create([
            'classroom_id' => $this->classroom->uuid, 'created_by' => $this->guruUser->uuid,
            'title' => 'Kuis Live', 'mode' => 'async', 'scoring_mode' => 'competitive',
            'max_score' => 100, 'status' => 'published', 'show_leaderboard' => true,
            'instant_feedback' => true,
        ]);
        $q1 = GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'mcq', 'question_text' => '2+2?',
            'points' => 1, 'sort_order' => 0,
        ]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => '4', 'is_correct' => true, 'sort_order' => 0]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => '5', 'is_correct' => false, 'sort_order' => 1]);

        $q2 = GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'short_answer', 'question_text' => 'Ibu kota RI?',
            'points' => 1, 'sort_order' => 1, 'meta' => ['answers' => ['Jakarta', 'DKI Jakarta']],
        ]);

        GameQuizAssignment::create([
            'quiz_id' => $this->quiz->uuid, 'classroom_id' => $this->classroom->uuid, 'status' => 'open',
        ]);
    }

    public function test_guru_advance_syncs_question_for_siswa(): void
    {
        Notification::fake();

        $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]))
            ->assertRedirect();

        Notification::assertSentTo($this->siswaUser, ArenaLiveStartedNotification::class);

        $this->actingAs($this->guruUser)
            ->postJson(route('classroom.arena.live.advance', [$this->classroom, $this->quiz]))
            ->assertOk();

        $session = GameLiveSession::where('quiz_id', $this->quiz->uuid)->latest()->first();
        $this->assertSame('question', $session->status);

        $state = $this->actingAs($this->siswaUser)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]))
            ->assertOk()
            ->json('session');

        $this->assertSame($session->current_question_id, $state['current_question_id']);
        $this->assertSame('question', $state['status']);
    }

    public function test_correct_answer_raises_leaderboard(): void
    {
        Notification::fake();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $this->quiz]));

        $session = GameLiveSession::latest()->first();
        $correct = GameQuestionOption::where('question_id', $session->current_question_id)->where('is_correct', true)->first();

        $this->actingAs($this->siswaUser)->postJson(route('classroom.arena.live.answer', [$this->classroom, $this->quiz]), [
            'question_id' => $session->current_question_id,
            'selected_option_id' => $correct->uuid,
        ])->assertOk()->assertJson(['ok' => true, 'is_correct' => true]);

        $board = $this->actingAs($this->siswaUser)
            ->getJson(route('classroom.arena.live.leaderboard', [$this->classroom, $this->quiz]))
            ->assertOk()
            ->json('leaderboard');

        $this->assertNotEmpty($board);
        $this->assertSame($this->siswaUser->uuid, $board[0]['student_id']);
        $this->assertGreaterThan(0, $board[0]['score']);
    }

    public function test_siswa_luar_cannot_answer_live(): void
    {
        Notification::fake();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $this->quiz]));
        $session = GameLiveSession::latest()->first();
        $opt = GameQuestionOption::where('question_id', $session->current_question_id)->first();

        $this->actingAs($this->otherSiswa)->postJson(route('classroom.arena.live.answer', [$this->classroom, $this->quiz]), [
            'question_id' => $session->current_question_id,
            'selected_option_id' => $opt->uuid,
        ])->assertStatus(403);
    }

    public function test_short_answer_and_match_grading(): void
    {
        $grader = new GameAnswerGrader;
        $short = GameQuestion::where('type', 'short_answer')->first();
        $this->assertTrue($grader->isCorrect($short, null, 'jakarta'));
        $this->assertTrue($grader->isCorrect($short, null, 'Jakart')); // fuzzy
        $this->assertFalse($grader->isCorrect($short, null, 'Bandung'));

        $match = GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'match', 'question_text' => 'Pasangkan',
            'points' => 2, 'sort_order' => 2,
            'meta' => ['pairs' => [
                ['left' => 'H2O', 'right' => 'Air'],
                ['left' => 'O2', 'right' => 'Oksigen'],
            ]],
        ]);
        $this->assertSame(1.0, $grader->matchRatio($match, json_encode(['H2O' => 'Air', 'O2' => 'Oksigen'])));
        $this->assertSame(0.5, $grader->matchRatio($match, json_encode(['H2O' => 'Air', 'O2' => 'Salah'])));
    }

    public function test_live_answer_locked_on_second_post(): void
    {
        Notification::fake();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $this->quiz]));

        $session = GameLiveSession::latest()->first();
        $wrong = GameQuestionOption::where('question_id', $session->current_question_id)->where('is_correct', false)->first();
        $correct = GameQuestionOption::where('question_id', $session->current_question_id)->where('is_correct', true)->first();

        $this->actingAs($this->siswaUser)->postJson(route('classroom.arena.live.answer', [$this->classroom, $this->quiz]), [
            'question_id' => $session->current_question_id,
            'selected_option_id' => $wrong->uuid,
        ])->assertOk();

        $this->actingAs($this->siswaUser)->postJson(route('classroom.arena.live.answer', [$this->classroom, $this->quiz]), [
            'question_id' => $session->current_question_id,
            'selected_option_id' => $correct->uuid,
        ])->assertStatus(409);

        $answer = GameAnswer::where('question_id', $session->current_question_id)->first();
        $this->assertSame($wrong->uuid, $answer->selected_option_id);
    }

    public function test_async_blocked_during_live_session(): void
    {
        Notification::fake();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));

        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $this->quiz]))
            ->assertStatus(403);
    }

    public function test_siswa_cannot_open_flashcard_template_with_keys(): void
    {
        $this->quiz->update(['template' => 'flashcard']);

        $this->actingAs($this->siswaUser)
            ->get(route('classroom.arena.template.play', [$this->classroom, $this->quiz]))
            ->assertStatus(403);
    }
}
