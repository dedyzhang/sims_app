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
        $this->assertTrue($quiz->is_locked);
        $this->assertNotEmpty($quiz->access_token);
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
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]), [
                'solo_token' => 'SOLO',
            ]);
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

    public function test_siswa_solo_requires_token(): void
    {
        $quiz = $this->makePublishedQuiz();

        $this->actingAs($this->siswaUser)
            ->from(route('classroom.arena.show', [$this->classroom, $quiz]))
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]))
            ->assertRedirect(route('classroom.arena.show', [$this->classroom, $quiz]))
            ->assertSessionHas('error');

        $this->assertNull(GameAttempt::where('student_id', $this->siswaUser->uuid)->first());

        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]), [
                'solo_token' => 'SALAH',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]), [
                'solo_token' => 'solo',
            ])
            ->assertRedirect(route('classroom.arena.play', [
                $this->classroom,
                $quiz,
                GameAttempt::where('student_id', $this->siswaUser->uuid)->first(),
            ]));
    }

    public function test_guru_can_close_and_reopen_quiz(): void
    {
        $quiz = $this->makePublishedQuiz();

        $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.close', [$this->classroom, $quiz]))
            ->assertRedirect();

        $quiz->refresh();
        $this->assertSame('closed', $quiz->status);
        $this->assertSame(
            'closed',
            GameQuizAssignment::where('quiz_id', $quiz->uuid)->value('status')
        );

        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]), ['solo_token' => 'SOLO'])
            ->assertStatus(403);

        // Hub experience tetap bisa dibuka (read-only) saat ditutup
        $this->actingAs($this->siswaUser)
            ->get(route('classroom.arena.show', [$this->classroom, $quiz]))
            ->assertOk();

        $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.reopen', [$this->classroom, $quiz]))
            ->assertRedirect();

        $quiz->refresh();
        $this->assertSame('published', $quiz->status);
        $this->assertTrue($quiz->is_locked);
        $this->assertSame('SOLO', $quiz->access_token);
        $this->assertSame(
            'open',
            GameQuizAssignment::where('quiz_id', $quiz->uuid)->value('status')
        );

        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]), ['solo_token' => 'SOLO'])
            ->assertRedirect();
    }

    public function test_close_ends_active_live_session(): void
    {
        $quiz = $this->makePublishedQuiz();
        $session = \App\Models\GameLiveSession::create([
            'quiz_id' => $quiz->uuid,
            'classroom_id' => $this->classroom->uuid,
            'hosted_by' => $this->guruUser->uuid,
            'status' => 'lobby',
            'started_at' => now(),
            'question_index' => 0,
        ]);

        $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.close', [$this->classroom, $quiz]))
            ->assertRedirect();

        $session->refresh();
        $this->assertSame('ended', $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_unpublish_to_draft_blocked_when_answers_exist(): void
    {
        $quiz = $this->makePublishedQuiz();
        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]), ['solo_token' => 'SOLO']);
        $attempt = GameAttempt::where('student_id', $this->siswaUser->uuid)->first();
        $q = $quiz->questions->first();
        $opt = $q->options->firstWhere('is_correct', true);
        \App\Models\GameAnswer::create([
            'attempt_id' => $attempt->uuid,
            'question_id' => $q->uuid,
            'selected_option_id' => $opt->uuid,
            'answered_at' => now(),
        ]);

        $this->actingAs($this->guruUser)
            ->from(route('classroom.arena.show', [$this->classroom, $quiz]))
            ->post(route('classroom.arena.draft', [$this->classroom, $quiz]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('published', $quiz->fresh()->status);
    }

    public function test_siswa_luar_cannot_play(): void
    {
        $quiz = $this->makePublishedQuiz();

        $response = $this->actingAs($this->otherSiswa)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]), [
                'solo_token' => 'SOLO',
            ]);

        $response->assertStatus(403);
    }

    public function test_submit_rejects_when_quiz_closed_even_if_assignment_due_null(): void
    {
        $quiz = $this->makePublishedQuiz();
        $quiz->update(['status' => 'closed']);

        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]), [
                'solo_token' => 'SOLO',
            ])
            ->assertStatus(403);
    }

    public function test_play_payload_hides_correct_flags(): void
    {
        $quiz = $this->makePublishedQuiz();
        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]), [
                'solo_token' => 'SOLO',
            ]);
        $attempt = GameAttempt::where('student_id', $this->siswaUser->uuid)->first();

        $response = $this->actingAs($this->siswaUser)
            ->get(route('classroom.arena.play', [$this->classroom, $quiz, $attempt]));

        $response->assertOk();
        $response->assertDontSee('"is_correct"', false);
        // Pastikan opsi teks tetap ada
        $response->assertSee('1');
        $response->assertSee('Solo · soal acak', false);
    }

    public function test_solo_play_shuffles_questions_per_attempt_but_stable_on_refresh(): void
    {
        $quiz = $this->makePublishedQuiz();
        foreach (['Q3', 'Q4', 'Q5', 'Q6'] as $i => $label) {
            $q = GameQuestion::create([
                'quiz_id' => $quiz->uuid, 'type' => 'mcq', 'question_text' => $label,
                'points' => 1, 'sort_order' => $i + 2,
            ]);
            GameQuestionOption::create(['question_id' => $q->uuid, 'option_text' => 'A', 'is_correct' => true, 'sort_order' => 0]);
            GameQuestionOption::create(['question_id' => $q->uuid, 'option_text' => 'B', 'is_correct' => false, 'sort_order' => 1]);
        }

        ClassroomMember::create([
            'classroom_id' => $this->classroom->uuid, 'user_id' => $this->otherSiswa->uuid,
            'role_in_class' => 'siswa', 'joined_at' => now(),
        ]);

        $this->actingAs($this->siswaUser)->post(route('classroom.arena.start', [$this->classroom, $quiz]), [
            'solo_token' => 'SOLO',
        ]);
        $this->actingAs($this->otherSiswa)->post(route('classroom.arena.start', [$this->classroom, $quiz]), [
            'solo_token' => 'SOLO',
        ]);

        $attemptA = GameAttempt::where('student_id', $this->siswaUser->uuid)->where('source', 'async')->firstOrFail();
        $attemptB = GameAttempt::where('student_id', $this->otherSiswa->uuid)->where('source', 'async')->firstOrFail();

        $rA1 = $this->actingAs($this->siswaUser)
            ->get(route('classroom.arena.play', [$this->classroom, $quiz, $attemptA]))
            ->assertOk()
            ->assertSee('Solo · soal acak', false);
        $orderA1 = collect($rA1->viewData('questionsPayload'))->pluck('uuid')->all();
        $optsA1 = collect($rA1->viewData('questionsPayload')->first()['options'] ?? [])->pluck('uuid')->all();

        $rA2 = $this->actingAs($this->siswaUser)
            ->get(route('classroom.arena.play', [$this->classroom, $quiz, $attemptA]))
            ->assertOk();
        $orderA2 = collect($rA2->viewData('questionsPayload'))->pluck('uuid')->all();
        $optsA2 = collect($rA2->viewData('questionsPayload')->first()['options'] ?? [])->pluck('uuid')->all();

        $rB = $this->actingAs($this->otherSiswa)
            ->get(route('classroom.arena.play', [$this->classroom, $quiz, $attemptB]))
            ->assertOk();
        $orderB = collect($rB->viewData('questionsPayload'))->pluck('uuid')->all();

        $this->assertSame($orderA1, $orderA2, 'Refresh harus menjaga urutan soal');
        $this->assertSame($optsA1, $optsA2, 'Refresh harus menjaga urutan opsi');
        $this->assertNotSame($orderA1, $orderB, 'Siswa berbeda harus dapat urutan berbeda');
        $this->assertEqualsCanonicalizing($orderA1, $orderB, 'Set soal harus sama, hanya urutannya beda');
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

    public function test_importer_applies_kunci_jawaban_section_from_asisten_guru(): void
    {
        $raw = <<<TXT
SOAL EVALUASI [MATEMATIKA]
Bagian A - Pilihan Ganda

1. Hasil 3 + 5 adalah?
A. 7
B. 8
C. 9
D. 10

2. Bumi berbentuk bola.
A. Benar
B. Salah

Kunci Jawaban & Pedoman Penilaian
1. B
2. Benar
TXT;
        $parsed = (new GameQuizImporter)->parse($raw);
        $this->assertCount(2, $parsed);
        $this->assertTrue(collect($parsed[0]['options'])->contains(fn ($o) => $o['option_text'] === '8' && $o['is_correct']));
        $this->assertTrue(collect($parsed[1]['options'])->contains(fn ($o) => $o['option_text'] === 'Benar' && $o['is_correct']));
    }

    public function test_importer_parses_isian_mencocokkan_and_pg_kompleks(): void
    {
        $raw = <<<TXT
1. Fotosintesis menghasilkan?
A. Oksigen
B. Karbon dioksida
C. Glukosa
D. Nitrogen
Petunjuk: pilih semua jawaban yang benar.

2. Cocokkan pernyataan pada Kolom A dengan jawaban pada Kolom B.
Kolom A:
1. Ibu kota Indonesia
2. Mata uang Indonesia
Kolom B:
A. Rupiah
B. Jakarta

3. Proses tumbuhan membuat makanan disebut ____.
Jawaban: ______________________________

Kunci Jawaban & Pedoman Penilaian
1. A, C
2. 1-B, 2-A
3. fotosintesis
TXT;
        $parsed = (new GameQuizImporter)->parse($raw);
        $this->assertCount(3, $parsed);

        $this->assertSame('mcq_complex', $parsed[0]['type']);
        $correct = collect($parsed[0]['options'])->where('is_correct', true)->pluck('option_text')->values()->all();
        $this->assertSame(['Oksigen', 'Glukosa'], $correct);

        $this->assertSame('match', $parsed[1]['type']);
        $this->assertCount(2, $parsed[1]['meta']['pairs']);
        $this->assertSame('Ibu kota Indonesia', $parsed[1]['meta']['pairs'][0]['left']);
        $this->assertSame('Jakarta', $parsed[1]['meta']['pairs'][0]['right']);

        $this->assertSame('short_answer', $parsed[2]['type']);
        $this->assertSame(['fotosintesis'], $parsed[2]['meta']['answers']);
    }

    public function test_create_form_loads_soal_dari_session_asisten_guru(): void
    {
        $raw = "1. Ibu kota?\nA. Bandung\nB. Jakarta\n\nKunci Jawaban\n1. B";

        $response = $this->actingAs($this->guruUser)
            ->withSession([
                'arena_ai_import' => [
                    'raw_text' => $raw,
                    'title' => 'Kuis: Geografi',
                ],
            ])
            ->get(route('classroom.arena.create', $this->classroom));

        $response->assertOk();
        $response->assertSee('Kuis: Geografi', false);
        $response->assertSee('Ibu kota?', false);
        $response->assertSee('Generate / impor soal (Asisten Guru)', false);
    }

    public function test_asisten_guru_kirim_ke_arena_redirects_ke_form_create(): void
    {
        $raw = "1. Ibu kota?\nA. Bandung\nB. Jakarta\n\nKunci Jawaban\n1. B";

        $response = $this->actingAs($this->guruUser)
            ->post(route('ai.teacher.quiz.send-arena'), [
                'classroom_id' => $this->classroom->uuid,
                'raw_text' => $raw,
                'title' => 'Kuis: Geografi',
            ]);

        $response->assertRedirect(route('classroom.arena.create', $this->classroom));
        $response->assertSessionHas('arena_ai_import.raw_text', $raw);
        $response->assertSessionHas('arena_ai_import.title', 'Kuis: Geografi');
    }

    public function test_kirim_ke_arena_ditolak_jika_teks_bukan_soal(): void
    {
        $response = $this->actingAs($this->guruUser)
            ->post(route('ai.teacher.quiz.send-arena'), [
                'classroom_id' => $this->classroom->uuid,
                'raw_text' => "RANGKUMAN MATERI\n- Fotosintesis adalah proses…",
                'title' => 'Bukan soal',
            ]);

        $response->assertRedirect(route('ai.teacher.index', ['tab' => 'quiz']));
        $response->assertSessionHas('error');
        $response->assertSessionMissing('arena_ai_import');
    }

    public function test_guru_can_copy_quiz_soal_to_other_classrooms(): void
    {
        $quiz = $this->makePublishedQuiz();
        $kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $kelasC = Kelas::create(['tingkat' => 7, 'kelas' => 'C']);
        $pelajaranId = $this->classroom->id_pelajaran;

        Ngajar::create([
            'id_guru' => $this->guru->uuid,
            'id_kelas' => $kelasB->uuid,
            'id_pelajaran' => $pelajaranId,
        ]);
        Ngajar::create([
            'id_guru' => $this->guru->uuid,
            'id_kelas' => $kelasC->uuid,
            'id_pelajaran' => $pelajaranId,
        ]);

        $classB = Classroom::create([
            'id_semester' => $this->classroom->id_semester,
            'id_kelas' => $kelasB->uuid,
            'id_pelajaran' => $pelajaranId,
            'title' => 'Matematika 7B',
            'status' => 'published',
            'class_code' => 'ARENA02',
            'created_by' => $this->guruUser->uuid,
            'cover_color' => '#2563eb',
        ]);
        $classC = Classroom::create([
            'id_semester' => $this->classroom->id_semester,
            'id_kelas' => $kelasC->uuid,
            'id_pelajaran' => $pelajaranId,
            'title' => 'Matematika 7C',
            'status' => 'published',
            'class_code' => 'ARENA03',
            'created_by' => $this->guruUser->uuid,
            'cover_color' => '#2563eb',
        ]);

        $this->actingAs($this->guruUser)
            ->get(route('classroom.arena.show', [$this->classroom, $quiz]))
            ->assertOk()
            ->assertSee('Salin soal ke kelas lain', false)
            ->assertSee('Matematika 7B', false);

        $response = $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.copy', [$this->classroom, $quiz]), [
                'classroom_ids' => [$classB->uuid, $classC->uuid],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $copyB = GameQuiz::where('classroom_id', $classB->uuid)->where('title', 'Kuis Uji')->first();
        $copyC = GameQuiz::where('classroom_id', $classC->uuid)->where('title', 'Kuis Uji')->first();
        $this->assertNotNull($copyB);
        $this->assertNotNull($copyC);
        $this->assertSame('draft', $copyB->status);
        $this->assertCount(2, $copyB->questions);
        $this->assertCount(4, $copyB->questions->first()->options);
        $this->assertNotSame($quiz->uuid, $copyB->uuid);
        $this->assertNotSame($quiz->questions->first()->uuid, $copyB->questions->first()->uuid);

        $srcQ = $quiz->questions()->orderBy('sort_order')->first();
        $dstQ = $copyB->questions()->orderBy('sort_order')->first();
        $this->assertSame($srcQ->question_text, $dstQ->question_text);
        $this->assertSame(
            $srcQ->options()->orderBy('sort_order')->pluck('is_correct')->map(fn ($v) => (bool) $v)->all(),
            $dstQ->options()->orderBy('sort_order')->pluck('is_correct')->map(fn ($v) => (bool) $v)->all()
        );
    }

    public function test_copy_rejects_different_mapel_classroom(): void
    {
        $quiz = $this->makePublishedQuiz();
        $kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $mapelLain = Pelajaran::create(['nama' => 'Fisika', 'ringkasan' => 'FIS', 'kkm' => 75]);
        Ngajar::create([
            'id_guru' => $this->guru->uuid,
            'id_kelas' => $kelasB->uuid,
            'id_pelajaran' => $mapelLain->uuid,
        ]);
        $classFisika = Classroom::create([
            'id_semester' => $this->classroom->id_semester,
            'id_kelas' => $kelasB->uuid,
            'id_pelajaran' => $mapelLain->uuid,
            'title' => 'Fisika 7B',
            'status' => 'published',
            'class_code' => 'ARENAFIS',
            'created_by' => $this->guruUser->uuid,
            'cover_color' => '#2563eb',
        ]);

        $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.copy', [$this->classroom, $quiz]), [
                'classroom_ids' => [$classFisika->uuid],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull(GameQuiz::where('classroom_id', $classFisika->uuid)->first());
    }

    public function test_siswa_cannot_copy_quiz(): void
    {
        $quiz = $this->makePublishedQuiz();
        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.copy', [$this->classroom, $quiz]), [
                'classroom_ids' => [$this->classroom->uuid],
            ])
            ->assertStatus(403);
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
            'is_locked'        => true,
            'access_token'     => 'SOLO',
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
