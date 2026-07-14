<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameAttempt;
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
use App\Services\GameQuizImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GameQuizTest extends TestCase
{
    use RefreshDatabase;

    protected User $guruUser;
    protected User $siswaUser;
    protected User $otherSiswa;
    protected Classroom $classroom;
    protected Guru $guru;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);

        $this->guruUser = User::create([
            'username' => 'guru_arena',
            'password' => Hash::make('password'),
            'access'   => 'guru',
        ]);
        $this->guru = Guru::create([
            'id_login'        => $this->guruUser->uuid,
            'nama'            => 'Guru Arena',
            'nik'             => '1000000001',
            'jk'              => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'ringkasan' => 'MTK', 'kkm' => 75]);

        Ngajar::create([
            'id_guru'      => $this->guru->uuid,
            'id_kelas'     => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);

        $this->classroom = Classroom::create([
            'id_semester'  => $semester->id,
            'id_kelas'     => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title'        => 'Matematika 7A',
            'status'       => 'published',
            'class_code'   => 'ARENA01',
            'created_by'   => $this->guruUser->uuid,
            'cover_color'  => '#2563eb',
        ]);

        $this->siswaUser = User::create([
            'username' => 'siswa_arena',
            'password' => Hash::make('password'),
            'access'   => 'siswa',
        ]);
        Siswa::create([
            'id_login'        => $this->siswaUser->uuid,
            'id_kelas'        => $kelas->uuid,
            'nama'            => 'Siswa Arena',
            'nis'             => '7001',
            'jk'              => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);
        ClassroomMember::create([
            'classroom_id'  => $this->classroom->uuid,
            'user_id'       => $this->siswaUser->uuid,
            'role_in_class' => 'siswa',
            'joined_at'     => now(),
        ]);

        $this->otherSiswa = User::create([
            'username' => 'siswa_luar',
            'password' => Hash::make('password'),
            'access'   => 'siswa',
        ]);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        Siswa::create([
            'id_login'        => $this->otherSiswa->uuid,
            'id_kelas'        => $kelasLain->uuid,
            'nama'            => 'Siswa Luar',
            'nis'             => '7002',
            'jk'              => 'P',
            'face_descriptor' => [0.1, 0.2],
        ]);
    }

    public function test_guru_can_create_quiz_with_questions(): void
    {
        $payload = [
            'title'            => 'Kuis Pecahan',
            'scoring_mode'     => 'accuracy',
            'max_score'        => 100,
            'instant_feedback' => 1,
            'publish_now'      => 1,
            'assign_self'      => 1,
            'questions'        => [
                [
                    'type'          => 'mcq',
                    'question_text' => '1/2 + 1/2 = ?',
                    'points'        => 1,
                    'options'       => [
                        ['option_text' => '1', 'is_correct' => 1],
                        ['option_text' => '2', 'is_correct' => 0],
                        ['option_text' => '0', 'is_correct' => 0],
                        ['option_text' => '1/4', 'is_correct' => 0],
                    ],
                ],
                [
                    'type'          => 'true_false',
                    'question_text' => '2 adalah bilangan genap.',
                    'points'        => 1,
                    'options'       => [
                        ['option_text' => 'Benar', 'is_correct' => 1],
                        ['option_text' => 'Salah', 'is_correct' => 0],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.store', $this->classroom), $payload);

        $quiz = GameQuiz::where('title', 'Kuis Pecahan')->first();
        $this->assertNotNull($quiz);
        $response->assertRedirect(route('classroom.arena.show', [$this->classroom, $quiz]));
        $this->assertSame('published', $quiz->status);
        $this->assertCount(2, $quiz->questions);
        $this->assertTrue(
            GameQuizAssignment::where('quiz_id', $quiz->uuid)
                ->where('classroom_id', $this->classroom->uuid)
                ->exists()
        );
    }

    public function test_siswa_can_attempt_and_get_score(): void
    {
        $quiz = $this->makePublishedQuiz();
        $correctOpt = $quiz->questions->first()->options->firstWhere('is_correct', true);

        $start = $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]));
        $start->assertRedirect();

        $attempt = GameAttempt::where('student_id', $this->siswaUser->uuid)->first();
        $this->assertNotNull($attempt);

        $answers = [];
        foreach ($quiz->questions as $q) {
            $opt = $q->options->firstWhere('is_correct', true);
            $answers[] = [
                'question_id'        => $q->uuid,
                'selected_option_id' => $opt->uuid,
            ];
        }

        $submit = $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.submit', [$this->classroom, $quiz, $attempt]), [
                'answers'     => $answers,
                'duration_ms' => 15000,
            ]);

        $submit->assertRedirect(route('classroom.arena.result', [$this->classroom, $quiz, $attempt]));
        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame(100, $attempt->total_score);
        $this->assertSame(2, $attempt->correct_count);
    }

    public function test_siswa_luar_cannot_play(): void
    {
        $quiz = $this->makePublishedQuiz();

        $response = $this->actingAs($this->otherSiswa)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]));

        $response->assertStatus(403);
    }

    public function test_submit_rejects_when_quiz_closed_even_if_assignment_due_null(): void
    {
        $quiz = $this->makePublishedQuiz();
        $quiz->update(['status' => 'closed']);

        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]))
            ->assertStatus(403);
    }

    public function test_play_payload_hides_correct_flags(): void
    {
        $quiz = $this->makePublishedQuiz();
        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]));
        $attempt = GameAttempt::where('student_id', $this->siswaUser->uuid)->first();

        $response = $this->actingAs($this->siswaUser)
            ->get(route('classroom.arena.play', [$this->classroom, $quiz, $attempt]));

        $response->assertOk();
        $response->assertDontSee('"is_correct"', false);
        // Pastikan opsi teks tetap ada
        $response->assertSee('1');
    }

    public function test_importer_parses_numbered_mcq(): void
    {
        $raw = <<<TXT
1. Ibu kota Indonesia?
A. Bandung
B. Jakarta *
C. Surabaya
D. Medan

2. 2+2=4
A. Benar
B. Salah
Kunci: A
TXT;
        $parsed = (new GameQuizImporter)->parse($raw);
        $this->assertCount(2, $parsed);
        $this->assertSame('mcq', $parsed[0]['type']);
        $this->assertTrue(collect($parsed[0]['options'])->contains(fn ($o) => $o['option_text'] === 'Jakarta' && $o['is_correct']));
    }

    private function makePublishedQuiz(): GameQuiz
    {
        $quiz = GameQuiz::create([
            'classroom_id'     => $this->classroom->uuid,
            'created_by'       => $this->guruUser->uuid,
            'title'            => 'Kuis Uji',
            'mode'             => 'async',
            'scoring_mode'     => 'accuracy',
            'max_score'        => 100,
            'instant_feedback' => true,
            'status'           => 'published',
        ]);

        $q1 = GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'mcq',
            'question_text' => '1/2 + 1/2 = ?',
            'points'        => 1,
            'sort_order'    => 0,
        ]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => '1', 'is_correct' => true, 'sort_order' => 0]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => '2', 'is_correct' => false, 'sort_order' => 1]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => '0', 'is_correct' => false, 'sort_order' => 2]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => '3', 'is_correct' => false, 'sort_order' => 3]);

        $q2 = GameQuestion::create([
            'quiz_id'       => $quiz->uuid,
            'type'          => 'true_false',
            'question_text' => '2 adalah bilangan genap.',
            'points'        => 1,
            'sort_order'    => 1,
        ]);
        GameQuestionOption::create(['question_id' => $q2->uuid, 'option_text' => 'Benar', 'is_correct' => true, 'sort_order' => 0]);
        GameQuestionOption::create(['question_id' => $q2->uuid, 'option_text' => 'Salah', 'is_correct' => false, 'sort_order' => 1]);

        GameQuizAssignment::create([
            'quiz_id'      => $quiz->uuid,
            'classroom_id' => $this->classroom->uuid,
            'status'       => 'open',
        ]);

        return $quiz->fresh(['questions.options']);
    }
}
