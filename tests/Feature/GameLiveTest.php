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
use Illuminate\Support\Facades\DB;
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

    /**
     * Keluhan FL: Arena Belajar berat saat siswa main. Root cause besar: sessionPayload()
     * (dibangun tiap poll state(), ~tiap 4 detik per siswa) dulu me-load SEMUA soal+opsi
     * kuis via $quiz->questions()->with('options')->get() cuma utk pakai SATU baris (soal
     * aktif) — makin banyak soal kuisnya, makin berat tiap poll, padahal isi soal yg lain
     * tak pernah dipakai. Test ini mengunci jumlah query state() TETAP walau kuisnya 20 soal.
     */
    public function test_query_state_tidak_naik_seiring_jumlah_soal_kuis(): void
    {
        Notification::fake();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $this->quiz]));

        // Poll sekali DULU di luar pengukuran — supaya baris GameLiveParticipant sudah ke-INSERT
        // (poll pertama beda jumlah query dari poll berikutnya krn ada INSERT itu). Kedua
        // pengukuran di bawah jadi sama-sama "poll ke-N", sesi & user yg identik — cuma jumlah
        // soal kuisnya yg beda, itu variabel satu-satunya yg mau diuji.
        $this->actingAs($this->siswaUser)->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]));

        DB::enableQueryLog();
        $this->actingAs($this->siswaUser)->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]))->assertOk();
        $queriesKecil = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Tambah 18 soal lagi (total 20) ke KUIS & SESI live yg SAMA yg sedang berjalan.
        for ($i = 0; $i < 18; $i++) {
            $q = GameQuestion::create([
                'quiz_id' => $this->quiz->uuid, 'type' => 'mcq', 'question_text' => "Soal tambahan {$i}",
                'points' => 1, 'sort_order' => 10 + $i,
            ]);
            for ($j = 0; $j < 4; $j++) {
                GameQuestionOption::create(['question_id' => $q->uuid, 'option_text' => "Opsi {$j}", 'is_correct' => $j === 0, 'sort_order' => $j]);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->siswaUser)->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]))->assertOk();
        $queriesBesar = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $queriesKecil,
            $queriesBesar,
            "Jumlah query state() harus SAMA persis kuis 2 soal vs 20 soal (skrg {$queriesKecil} vs {$queriesBesar}) — indikasi load-semua-soal per poll kembali muncul."
        );
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

    /**
     * Bug dilaporkan FL: podium beda antara guru & siswa. Root cause: saat hide_scores aktif,
     * urutan siswa dulu SAMA SEKALI tak di-sort (skip sortByDesc), sementara guru (yg selalu
     * lolos !hideScores) tetap ter-sort — dua penampil, dua urutan berbeda. Sekarang sorting
     * selalu terjadi via ORDER BY di query (bukan collection sort kondisional) — cuma field
     * score/correct yg disembunyikan dari payload siswa, urutan barisnya harus tetap identik.
     */
    public function test_podium_urutan_sama_guru_dan_siswa_walau_hide_scores(): void
    {
        $this->quiz->update(['hide_scores' => true]);
        $siswaB = User::create(['username' => 'siswa_live_b', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswaB->uuid, 'id_kelas' => $this->classroom->id_kelas, 'nama' => 'Siswa Live B', 'nis' => '8003', 'jk' => 'P', 'face_descriptor' => [0.1]]);
        ClassroomMember::create(['classroom_id' => $this->classroom->uuid, 'user_id' => $siswaB->uuid, 'role_in_class' => 'siswa', 'joined_at' => now()]);

        Notification::fake();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $this->quiz]));
        $session = GameLiveSession::latest()->first();
        $correct = GameQuestionOption::where('question_id', $session->current_question_id)->where('is_correct', true)->first();
        $wrong = GameQuestionOption::where('question_id', $session->current_question_id)->where('is_correct', false)->first();

        // siswaUser jawab benar (skor > 0), siswaB jawab salah (skor 0) — beda peringkat jelas.
        $this->actingAs($this->siswaUser)->postJson(route('classroom.arena.live.answer', [$this->classroom, $this->quiz]), [
            'question_id' => $session->current_question_id, 'selected_option_id' => $correct->uuid,
        ])->assertOk();
        $this->actingAs($siswaB)->postJson(route('classroom.arena.live.answer', [$this->classroom, $this->quiz]), [
            'question_id' => $session->current_question_id, 'selected_option_id' => $wrong->uuid,
        ])->assertOk();

        $guruBoard = $this->actingAs($this->guruUser)
            ->getJson(route('classroom.arena.live.leaderboard', [$this->classroom, $this->quiz]))
            ->assertOk()->json('leaderboard');
        $siswaBoard = $this->actingAs($this->siswaUser)
            ->getJson(route('classroom.arena.live.leaderboard', [$this->classroom, $this->quiz]))
            ->assertOk()->json();

        $this->assertTrue($siswaBoard['scores_hidden']);
        $this->assertArrayNotHasKey('score', $siswaBoard['leaderboard'][0], 'Siswa tak boleh lihat skor saat hide_scores aktif.');
        $this->assertSame(
            array_column($guruBoard, 'student_id'),
            array_column($siswaBoard['leaderboard'], 'student_id'),
            'Urutan podium (siapa di posisi ke berapa) harus SAMA antara guru & siswa, walau siswa tak lihat angka skornya.'
        );
        $this->assertSame($this->siswaUser->uuid, $guruBoard[0]['student_id'], 'Yang jawab benar harus di posisi 1.');
    }

    /**
     * Bug dilaporkan FL: podium beda-beda tiap soal. Salah satu penyebab: query leaderboard
     * dulu TANPA ORDER BY sama sekali di level SQL (cuma sortByDesc('score') di collection,
     * itu pun cuma kalau !hideScores) — utk siswa yg skornya SERI, urutan tak dijamin stabil
     * antar-request. Sekarang ada tiebreak eksplisit (updated_at lalu uuid) — harus stabil.
     */
    public function test_podium_urutan_stabil_walau_skor_seri(): void
    {
        $siswaB = User::create(['username' => 'siswa_live_seri', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswaB->uuid, 'id_kelas' => $this->classroom->id_kelas, 'nama' => 'Siswa Seri', 'nis' => '8004', 'jk' => 'P', 'face_descriptor' => [0.1]]);
        ClassroomMember::create(['classroom_id' => $this->classroom->uuid, 'user_id' => $siswaB->uuid, 'role_in_class' => 'siswa', 'joined_at' => now()]);

        Notification::fake();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $this->quiz]));
        $session = GameLiveSession::latest()->first();
        $correct = GameQuestionOption::where('question_id', $session->current_question_id)->where('is_correct', true)->first();

        // Kedua siswa jawab BENAR — skor seri.
        $this->actingAs($this->siswaUser)->postJson(route('classroom.arena.live.answer', [$this->classroom, $this->quiz]), [
            'question_id' => $session->current_question_id, 'selected_option_id' => $correct->uuid,
        ])->assertOk();
        $this->actingAs($siswaB)->postJson(route('classroom.arena.live.answer', [$this->classroom, $this->quiz]), [
            'question_id' => $session->current_question_id, 'selected_option_id' => $correct->uuid,
        ])->assertOk();

        $first = $this->actingAs($this->guruUser)
            ->getJson(route('classroom.arena.live.leaderboard', [$this->classroom, $this->quiz]))
            ->assertOk()->json('leaderboard');
        $second = $this->actingAs($this->guruUser)
            ->getJson(route('classroom.arena.live.leaderboard', [$this->classroom, $this->quiz]))
            ->assertOk()->json('leaderboard');

        $this->assertSame($first[0]['score'], $first[1]['score'], 'Sengaja seri utk tes ini.');
        $this->assertSame(
            array_column($first, 'student_id'),
            array_column($second, 'student_id'),
            'Urutan podium utk siswa yg skornya seri harus tetap sama di panggilan berikutnya.'
        );
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

    /**
     * Bug dilaporkan FL: soal mencocokkan teracak ULANG saat siswa lagi menjawab, jadi opsi
     * yg diincar berpindah posisi. Root cause: publicMeta() dulu pakai shuffle() polos yg
     * dipanggil ulang tiap sessionPayload() dibangun — yaitu tiap poll live/state (~4 detik)
     * selama soal aktif. Sekarang di-seed per (sesi, soal) — urutan HARUS sama di seluruh
     * poll selama soal yg sama masih aktif.
     */
    public function test_urutan_opsi_soal_mencocokkan_stabil_antar_poll(): void
    {
        GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'match', 'question_text' => 'Pasangkan',
            'points' => 2, 'sort_order' => 2,
            'meta' => ['pairs' => [
                ['left' => 'H2O', 'right' => 'Air'],
                ['left' => 'O2', 'right' => 'Oksigen'],
                ['left' => 'CO2', 'right' => 'Karbon Dioksida'],
                ['left' => 'N2', 'right' => 'Nitrogen'],
            ]],
        ]);

        Notification::fake();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));
        // Maju sampai soal ke-3 (match): lobby->q1(mcq), q1->reveal, reveal->standings,
        // standings->q2(short_answer), q2->reveal, reveal->standings, standings->q3(match).
        for ($i = 0; $i < 7; $i++) {
            $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $this->quiz]));
        }
        $session = GameLiveSession::latest()->first();
        $this->assertSame('question', $session->status);
        $this->assertSame(2, $session->question_index);

        $first = $this->actingAs($this->siswaUser)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]))
            ->assertOk()->json('session.question.meta.rights');
        $second = $this->actingAs($this->siswaUser)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]))
            ->assertOk()->json('session.question.meta.rights');
        $thirdByOtherViewer = $this->actingAs($this->guruUser)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]))
            ->assertOk()->json('session.question.meta.rights');

        $this->assertSame($first, $second, 'Urutan opsi mencocokkan harus sama di poll berikutnya (siswa yg sama).');
        $this->assertSame($first, $thirdByOtherViewer, 'Urutan opsi mencocokkan harus sama walau dilihat guru vs siswa.');
        $this->assertEqualsCanonicalizing(['Air', 'Oksigen', 'Karbon Dioksida', 'Nitrogen'], $first, 'Tetap semua opsi asli, cuma urutannya yg diacak.');
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
