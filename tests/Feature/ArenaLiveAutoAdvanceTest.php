<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameLiveSession;
use App\Models\GameQuestion;
use App\Models\GameQuestionOption;
use App\Models\GameQuiz;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fitur baru Arena Belajar: (1) guru pilih cara main (solo/live/bebas) saat buat kuis,
 * (2) tiap soal boleh punya batas waktu, (3) sesi live otomatis maju — waktu soal habis ATAU
 * semua siswa yg tercatat "masuk" sesi sudah jawab — lalu mampir sebentar ke pembahasan &
 * papan peringkat sebelum lanjut ke soal berikutnya (atau selesai).
 */
class ArenaLiveAutoAdvanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $guruUser;
    protected User $siswaA;
    protected User $siswaB;
    protected Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);

        $this->guruUser = User::create(['username' => 'guru_auto', 'password' => Hash::make('x'), 'access' => 'guru']);
        $guru = Guru::create([
            'id_login' => $this->guruUser->uuid, 'nama' => 'Guru Auto', 'nik' => '3001', 'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 9, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'IPS', 'ringkasan' => 'IPS', 'kkm' => 75]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_kelas' => $kelas->uuid, 'id_pelajaran' => $pelajaran->uuid]);

        $this->classroom = Classroom::create([
            'id_semester' => $semester->id, 'id_kelas' => $kelas->uuid, 'id_pelajaran' => $pelajaran->uuid,
            'title' => 'IPS 9A', 'status' => 'published', 'class_code' => 'AUTO01',
            'created_by' => $this->guruUser->uuid,
        ]);

        $this->siswaA = User::create(['username' => 'siswa_auto_a', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $this->siswaA->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa A', 'nis' => '9001', 'jk' => 'L', 'face_descriptor' => [0.1]]);
        ClassroomMember::create(['classroom_id' => $this->classroom->uuid, 'user_id' => $this->siswaA->uuid, 'role_in_class' => 'siswa', 'joined_at' => now()]);

        $this->siswaB = User::create(['username' => 'siswa_auto_b', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $this->siswaB->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa B', 'nis' => '9002', 'jk' => 'P', 'face_descriptor' => [0.1]]);
        ClassroomMember::create(['classroom_id' => $this->classroom->uuid, 'user_id' => $this->siswaB->uuid, 'role_in_class' => 'siswa', 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeQuiz(?int $timeLimit = null, string $playMode = 'bebas'): GameQuiz
    {
        $quiz = GameQuiz::create([
            'classroom_id' => $this->classroom->uuid, 'created_by' => $this->guruUser->uuid,
            'title' => 'Kuis Auto', 'mode' => 'async', 'play_mode' => $playMode,
            'scoring_mode' => 'accuracy', 'max_score' => 100, 'status' => 'published',
        ]);
        $q1 = GameQuestion::create([
            'quiz_id' => $quiz->uuid, 'type' => 'mcq', 'question_text' => 'Soal 1',
            'points' => 1, 'sort_order' => 0, 'time_limit_seconds' => $timeLimit,
        ]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => 'B', 'is_correct' => false, 'sort_order' => 1]);

        $q2 = GameQuestion::create([
            'quiz_id' => $quiz->uuid, 'type' => 'mcq', 'question_text' => 'Soal 2',
            'points' => 1, 'sort_order' => 1,
        ]);
        GameQuestionOption::create(['question_id' => $q2->uuid, 'option_text' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        GameQuestionOption::create(['question_id' => $q2->uuid, 'option_text' => 'B', 'is_correct' => false, 'sort_order' => 1]);

        return $quiz;
    }

    public function test_play_mode_tersimpan_saat_buat_kuis(): void
    {
        $response = $this->actingAs($this->guruUser)->post(route('classroom.arena.store', $this->classroom), [
            'title' => 'Kuis Live Saja',
            'scoring_mode' => 'accuracy',
            'play_mode' => 'live',
            'max_score' => 100,
            'questions' => [[
                'type' => 'mcq', 'question_text' => 'Soal?', 'points' => 1,
                'options' => [
                    ['option_text' => 'A', 'is_correct' => 1],
                    ['option_text' => 'B', 'is_correct' => 0],
                ],
            ]],
        ]);
        $response->assertRedirect();

        $quiz = GameQuiz::where('title', 'Kuis Live Saja')->firstOrFail();
        $this->assertSame('live', $quiz->play_mode);
        $this->assertFalse($quiz->allowsSolo());
        $this->assertTrue($quiz->allowsLive());
    }

    public function test_siswa_tidak_bisa_main_solo_jika_play_mode_live(): void
    {
        $quiz = $this->makeQuiz(playMode: 'live');

        $this->actingAs($this->siswaA)
            ->post(route('classroom.arena.start', [$this->classroom, $quiz]))
            ->assertStatus(403);
    }

    public function test_guru_tidak_bisa_mulai_live_jika_play_mode_solo(): void
    {
        $quiz = $this->makeQuiz(playMode: 'solo');

        $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.live.start', [$this->classroom, $quiz]))
            ->assertStatus(422);
    }

    public function test_waktu_soal_habis_otomatis_maju_ke_reveal(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));
        $quiz = $this->makeQuiz(timeLimit: 10);

        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz]));

        $session = GameLiveSession::latest()->first();
        $this->assertSame('question', $session->status);
        $this->assertNotNull($session->question_deadline_at);

        // Waktu belum habis — status masih 'question'.
        $state = $this->actingAs($this->siswaA)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]))
            ->assertOk()->json('session');
        $this->assertSame('question', $state['status']);

        // Lompat lewati batas waktu 10 detik.
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:11'));
        $state = $this->actingAs($this->siswaA)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]))
            ->assertOk()->json('session');
        $this->assertSame('reveal', $state['status']);
    }

    public function test_semua_yg_masuk_sudah_jawab_otomatis_maju_ke_reveal(): void
    {
        $quiz = $this->makeQuiz();

        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz]));
        $session = GameLiveSession::latest()->first();
        $optA = GameQuestionOption::where('question_id', $session->current_question_id)->where('is_correct', true)->first();

        // Kedua siswa "masuk" (poll state()) tapi belum jawab — status tetap 'question'.
        $this->actingAs($this->siswaA)->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]));
        $this->actingAs($this->siswaB)->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]));
        $this->assertSame('question', GameLiveSession::latest()->first()->status);

        // Siswa A jawab — belum semua (siswa B belum) — tetap 'question'.
        $this->actingAs($this->siswaA)->postJson(route('classroom.arena.live.answer', [$this->classroom, $quiz]), [
            'question_id' => $session->current_question_id,
            'selected_option_id' => $optA->uuid,
        ])->assertOk();
        $this->assertSame('question', GameLiveSession::latest()->first()->status);

        // Siswa B jawab juga — SEMUA yg masuk sudah jawab -> otomatis maju ke 'reveal'.
        $this->actingAs($this->siswaB)->postJson(route('classroom.arena.live.answer', [$this->classroom, $quiz]), [
            'question_id' => $session->current_question_id,
            'selected_option_id' => $optA->uuid,
        ])->assertOk();
        $this->assertSame('reveal', GameLiveSession::latest()->first()->status);
    }

    public function test_siswa_yg_belum_pernah_poll_tidak_dihitung_jadi_penyebut(): void
    {
        // Cuma siswa A yg "masuk" (poll) sesi ini — siswa B tak pernah buka halaman live.
        // Begitu siswa A saja sudah jawab, itu SUDAH "semua yg masuk" -> otomatis maju.
        $quiz = $this->makeQuiz();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz]));
        $session = GameLiveSession::latest()->first();
        $optA = GameQuestionOption::where('question_id', $session->current_question_id)->where('is_correct', true)->first();

        $this->actingAs($this->siswaA)->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]));

        $this->actingAs($this->siswaA)->postJson(route('classroom.arena.live.answer', [$this->classroom, $quiz]), [
            'question_id' => $session->current_question_id,
            'selected_option_id' => $optA->uuid,
        ])->assertOk();

        $this->assertSame('reveal', GameLiveSession::latest()->first()->status);
    }

    public function test_reveal_otomatis_maju_ke_standings_setelah_jeda(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 09:00:00'));
        $quiz = $this->makeQuiz();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz]));
        $this->assertSame('reveal', GameLiveSession::latest()->first()->status);

        Carbon::setTestNow(Carbon::parse('2026-01-01 09:00:05'));
        $state = $this->actingAs($this->siswaA)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]))
            ->assertOk()->json('session');
        $this->assertSame('standings', $state['status']);
    }

    public function test_standings_otomatis_maju_ke_soal_berikutnya(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        $quiz = $this->makeQuiz();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $quiz]));
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz])); // -> question (soal 1)
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz])); // -> reveal
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz])); // -> standings
        $session = GameLiveSession::latest()->first();
        $this->assertSame('standings', $session->status);
        $this->assertSame(0, $session->question_index);

        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:10'));
        $state = $this->actingAs($this->siswaA)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]))
            ->assertOk()->json('session');
        $this->assertSame('question', $state['status']);
        $this->assertSame(1, $state['question_index']);
    }

    public function test_standings_di_soal_terakhir_otomatis_maju_ke_ended(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 11:00:00'));
        $quiz = $this->makeQuiz();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $quiz]));
        // Maju sampai soal ke-2 (terakhir): lobby->q1, q1->reveal, reveal->standings, standings->q2
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz]));
        }
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz])); // q2 -> reveal
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.live.advance', [$this->classroom, $quiz])); // reveal -> standings
        $session = GameLiveSession::latest()->first();
        $this->assertSame('standings', $session->status);
        $this->assertSame(1, $session->question_index);

        Carbon::setTestNow(Carbon::parse('2026-01-01 11:00:10'));
        $state = $this->actingAs($this->siswaA)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]))
            ->assertOk()->json('session');
        $this->assertSame('ended', $state['status']);
    }

    public function test_time_limit_seconds_tersimpan_dan_disalin_ke_kelas_lain(): void
    {
        $quiz = $this->makeQuiz(timeLimit: 20);

        // Cukup created_by yg menyamai guru ini utk lolos ClassroomPolicy::manage() — TIDAK perlu
        // baris Ngajar (kalau ditambah, NgajarObserver otomatis bikin kuis demo "draft" duluan di
        // classroom ini sebelum copy jalan, bikin ada 2 GameQuiz utk classroom yg sama).
        $kelasLain = Kelas::create(['tingkat' => 9, 'kelas' => 'B']);
        $target = Classroom::create([
            'id_semester' => Semester::first()->id, 'id_kelas' => $kelasLain->uuid,
            'id_pelajaran' => $this->classroom->id_pelajaran, 'title' => 'IPS 9B',
            'status' => 'draft', 'class_code' => 'AUTO02', 'created_by' => $this->guruUser->uuid,
        ]);

        $this->actingAs($this->guruUser)->post(route('classroom.arena.copy', [$this->classroom, $quiz]), [
            'classroom_ids' => [$target->uuid],
        ])->assertRedirect();

        $copied = GameQuiz::where('classroom_id', $target->uuid)->firstOrFail();
        $firstQuestion = $copied->questions()->orderBy('sort_order')->first();
        $this->assertSame(20, $firstQuestion->time_limit_seconds);
    }

    public function test_siswa_yang_poll_muncul_online_di_state(): void
    {
        $quiz = $this->makeQuiz();
        $this->actingAs($this->guruUser)->post(route('classroom.arena.live.start', [$this->classroom, $quiz]));

        $lobbyGuru = $this->actingAs($this->guruUser)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]))
            ->assertOk()
            ->json('session');
        $this->assertSame('lobby', $lobbyGuru['status']);
        $this->assertSame(0, $lobbyGuru['online_count']);
        $this->assertSame([], $lobbyGuru['participants']);

        $this->actingAs($this->siswaA)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]))
            ->assertOk();

        $state = $this->actingAs($this->guruUser)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $quiz]))
            ->assertOk()
            ->json('session');

        $this->assertSame(1, $state['online_count']);
        $this->assertCount(1, $state['participants']);
        $this->assertSame($this->siswaA->uuid, $state['participants'][0]['user_id']);
        $this->assertTrue($state['participants'][0]['online']);
        $this->assertSame('Siswa A', $state['participants'][0]['name']);
    }
}
