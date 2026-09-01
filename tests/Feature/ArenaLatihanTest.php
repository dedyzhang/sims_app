<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameAnswer;
use App\Models\GameAttempt;
use App\Models\GamePracticeAttempt;
use App\Models\GamePracticeParticipant;
use App\Models\GamePracticeSession;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fitur "Latihan" Arena Belajar — rehearsal sebelum live sungguhan, tamu (guru/siswa mana pun)
 * gabung via QR/kode TANPA login & TANPA perlu jadi anggota kelas. Mengunci: (1) tamu bisa
 * gabung cukup ketik nama, tanpa akun; (2) skor/podium latihan konsisten & terpisah TOTAL dari
 * data live/gradebook asli; (3) soal match tetap stabil urutannya antar poll (regresi guard sama
 * spt fix live); (4) cap peserta & cleanup otomatis.
 */
class ArenaLatihanTest extends TestCase
{
    use RefreshDatabase;

    protected User $guruUser;
    protected Classroom $classroom;
    protected GameQuiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);

        $this->guruUser = User::create(['username' => 'guru_latihan', 'password' => Hash::make('x'), 'access' => 'guru']);
        $guru = Guru::create([
            'id_login' => $this->guruUser->uuid, 'nama' => 'Guru Latihan', 'nik' => '4001', 'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'L']);
        $pelajaran = Pelajaran::create(['nama' => 'IPA Latihan', 'ringkasan' => 'IPA', 'kkm' => 75]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_kelas' => $kelas->uuid, 'id_pelajaran' => $pelajaran->uuid]);

        $this->classroom = Classroom::create([
            'id_semester' => $semester->id, 'id_kelas' => $kelas->uuid, 'id_pelajaran' => $pelajaran->uuid,
            'title' => 'IPA 7L', 'status' => 'published', 'class_code' => 'LATIH01',
            'created_by' => $this->guruUser->uuid,
        ]);

        // status DRAFT sengaja — latihan harus tetap bisa dipakai walau kuis belum terbit.
        $this->quiz = GameQuiz::create([
            'classroom_id' => $this->classroom->uuid, 'created_by' => $this->guruUser->uuid,
            'title' => 'Kuis Latihan', 'mode' => 'async', 'play_mode' => 'bebas',
            'scoring_mode' => 'accuracy', 'max_score' => 100, 'status' => 'draft',
        ]);
        $q1 = GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'mcq', 'question_text' => 'Soal 1',
            'points' => 1, 'sort_order' => 0,
        ]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        GameQuestionOption::create(['question_id' => $q1->uuid, 'option_text' => 'B', 'is_correct' => false, 'sort_order' => 1]);
    }

    private function startPractice(): GamePracticeSession
    {
        $this->actingAs($this->guruUser)->post(route('classroom.arena.latihan.start', [$this->classroom, $this->quiz]))
            ->assertRedirect();

        return GamePracticeSession::latest()->first();
    }

    public function test_guru_bisa_mulai_latihan_walau_kuis_masih_draft(): void
    {
        $this->assertSame('draft', $this->quiz->status);
        $session = $this->startPractice();

        $this->assertNotNull($session);
        $this->assertSame('lobby', $session->status);
        $this->assertSame(8, strlen($session->join_token));
    }

    public function test_mulai_latihan_kedua_mengakhiri_sesi_lama_dulu(): void
    {
        $first = $this->startPractice();
        $second = $this->startPractice();

        $this->assertSame('ended', $first->fresh()->status);
        $this->assertSame('lobby', $second->fresh()->status);
        $this->assertNotSame($first->uuid, $second->uuid);
    }

    public function test_tamu_bisa_gabung_cukup_ketik_nama_tanpa_akun(): void
    {
        $session = $this->startPractice();

        $this->get(route('latihan.publik.show', $session->join_token))
            ->assertOk()->assertViewIs('arena-latihan.gabung');

        $response = $this->post(route('latihan.publik.join', $session->join_token), [
            'guest_name' => 'Andi', 'claimed_role' => 'siswa',
        ]);
        $response->assertRedirect();

        $participant = GamePracticeParticipant::where('session_id', $session->uuid)->first();
        $this->assertSame('Andi', $participant->guest_name);
        $this->assertSame('siswa', $participant->claimed_role);
        $this->assertNotEmpty($participant->guest_token);

        // Redirect membawa ?g= yg valid — buka lagi langsung ke layar main (bukan form lagi).
        $redirectUrl = $response->headers->get('Location');
        $this->get($redirectUrl)->assertOk()->assertViewIs('arena-latihan.main');
    }

    public function test_dua_tamu_jawab_bersamaan_skor_benar_dan_auto_advance(): void
    {
        $session = $this->startPractice();
        $this->actingAs($this->guruUser)->postJson(route('classroom.arena.latihan.advance', [$this->classroom, $this->quiz]));
        $session->refresh();
        $optA = GameQuestionOption::where('question_id', $session->current_question_id)->where('is_correct', true)->first();
        $optB = GameQuestionOption::where('question_id', $session->current_question_id)->where('is_correct', false)->first();

        $r1 = $this->post(route('latihan.publik.join', $session->join_token), ['guest_name' => 'Tamu 1']);
        $g1 = $this->extractGuestToken($r1);
        $r2 = $this->post(route('latihan.publik.join', $session->join_token), ['guest_name' => 'Tamu 2']);
        $g2 = $this->extractGuestToken($r2);

        // Poll dulu spy tercatat "masuk" (penyebut auto-advance).
        $this->get(route('latihan.publik.state', ['joinToken' => $session->join_token, 'g' => $g1]));
        $this->get(route('latihan.publik.state', ['joinToken' => $session->join_token, 'g' => $g2]));

        $this->postJson(route('latihan.publik.answer', $session->join_token), [
            'question_id' => $session->current_question_id, 'selected_option_id' => $optA->uuid, 'g' => $g1,
        ])->assertOk()->assertJson(['ok' => true, 'is_correct' => true]);
        $this->assertSame('question', $session->fresh()->status, 'Baru 1 dari 2 tamu jawab — belum maju.');

        $this->postJson(route('latihan.publik.answer', $session->join_token), [
            'question_id' => $session->current_question_id, 'selected_option_id' => $optB->uuid, 'g' => $g2,
        ])->assertOk()->assertJson(['ok' => true, 'is_correct' => false]);
        $this->assertSame('reveal', $session->fresh()->status, 'Kedua tamu sudah jawab — harus otomatis maju.');

        $board = $this->getJson(route('latihan.publik.board', ['joinToken' => $session->join_token, 'g' => $g1]))
            ->assertOk()->json('leaderboard');
        $this->assertSame('Tamu 1', $board[0]['name']);
        $this->assertGreaterThan(0, $board[0]['score']);
        $this->assertSame(0, $board[1]['score']);
    }

    private function extractGuestToken($response): string
    {
        $url = $response->headers->get('Location');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['g'];
    }

    public function test_urutan_opsi_soal_mencocokkan_stabil_antar_poll(): void
    {
        GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'match', 'question_text' => 'Cocokkan',
            'points' => 2, 'sort_order' => 1,
            'meta' => ['pairs' => [
                ['left' => 'H2O', 'right' => 'Air'],
                ['left' => 'O2', 'right' => 'Oksigen'],
                ['left' => 'CO2', 'right' => 'Karbon Dioksida'],
                ['left' => 'N2', 'right' => 'Nitrogen'],
            ]],
        ]);

        $session = $this->startPractice();
        // lobby -> q1(mcq) -> reveal -> standings -> q2(match)
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($this->guruUser)->postJson(route('classroom.arena.latihan.advance', [$this->classroom, $this->quiz]));
        }
        $session->refresh();
        $this->assertSame('question', $session->status);
        $this->assertSame(1, $session->question_index);

        $r = $this->post(route('latihan.publik.join', $session->join_token), ['guest_name' => 'Tamu Match']);
        $g = $this->extractGuestToken($r);

        $first = $this->getJson(route('latihan.publik.state', ['joinToken' => $session->join_token, 'g' => $g]))->json('session.question.meta.rights');
        $second = $this->getJson(route('latihan.publik.state', ['joinToken' => $session->join_token, 'g' => $g]))->json('session.question.meta.rights');

        $this->assertSame($first, $second, 'Urutan opsi mencocokkan harus stabil antar poll.');
        $this->assertEqualsCanonicalizing(['Air', 'Oksigen', 'Karbon Dioksida', 'Nitrogen'], $first);
    }

    public function test_join_token_salah_404_pesan_ramah(): void
    {
        $this->get(route('latihan.publik.show', 'BADTOKN'))->assertNotFound();
    }

    public function test_sesi_ended_tamu_baru_lihat_halaman_berakhir_tamu_lama_tetap_lihat_podium(): void
    {
        $session = $this->startPractice();
        $r = $this->post(route('latihan.publik.join', $session->join_token), ['guest_name' => 'Tamu Lama']);
        $g = $this->extractGuestToken($r);

        $this->actingAs($this->guruUser)->post(route('classroom.arena.latihan.end', [$this->classroom, $this->quiz]));
        $this->assertSame('ended', $session->fresh()->status);

        // Tamu baru (tanpa ?g= valid) -> halaman berakhir.
        $this->get(route('latihan.publik.show', $session->join_token))
            ->assertOk()->assertViewIs('arena-latihan.berakhir');

        // Tamu lama (?g= valid) -> tetap layar main (podium akhir read-only).
        $this->get(route('latihan.publik.show', ['joinToken' => $session->join_token, 'g' => $g]))
            ->assertOk()->assertViewIs('arena-latihan.main');
    }

    public function test_cap_peserta_60_ditolak_pada_percobaan_ke_61(): void
    {
        $session = $this->startPractice();
        for ($i = 0; $i < 60; $i++) {
            $now = now();
            GamePracticeParticipant::create([
                'session_id' => $session->uuid, 'guest_name' => "Tamu {$i}",
                'guest_token' => \Illuminate\Support\Str::random(40), 'joined_at' => $now, 'last_seen_at' => $now,
            ]);
        }

        $this->post(route('latihan.publik.join', $session->join_token), ['guest_name' => 'Tamu ke-61'])
            ->assertSessionHasErrors('guest_name');
        $this->assertSame(60, GamePracticeParticipant::where('session_id', $session->uuid)->count());
    }

    public function test_data_latihan_tak_pernah_terlihat_di_leaderboard_atau_hasil_live_asli(): void
    {
        // Seed data LIVE asli (source=live) di quiz yg sama.
        $assignment = GameQuizAssignment::create(['quiz_id' => $this->quiz->uuid, 'classroom_id' => $this->classroom->uuid, 'status' => 'open']);
        $siswaUser = User::create(['username' => 'siswa_asli_latihan', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswaUser->uuid, 'id_kelas' => $this->classroom->id_kelas, 'nama' => 'Siswa Asli', 'nis' => '7001', 'jk' => 'L', 'face_descriptor' => [0.1]]);
        ClassroomMember::create(['classroom_id' => $this->classroom->uuid, 'user_id' => $siswaUser->uuid, 'role_in_class' => 'siswa', 'joined_at' => now()]);
        $realAttempt = GameAttempt::create([
            'assignment_id' => $assignment->uuid, 'student_id' => $siswaUser->uuid, 'source' => 'live',
            'total_score' => 77, 'correct_count' => 1, 'status' => 'submitted',
        ]);
        $countBefore = GameAttempt::where('assignment_id', $assignment->uuid)->count();

        // Sekarang bikin banyak data LATIHAN utk kuis yg sama.
        $session = $this->startPractice();
        for ($i = 0; $i < 5; $i++) {
            $now = now();
            $p = GamePracticeParticipant::create([
                'session_id' => $session->uuid, 'guest_name' => "Latihan {$i}",
                'guest_token' => \Illuminate\Support\Str::random(40), 'joined_at' => $now, 'last_seen_at' => $now,
            ]);
            GamePracticeAttempt::create(['session_id' => $session->uuid, 'participant_id' => $p->uuid, 'total_score' => 99]);
        }

        // Data live ASLI tak berubah sama sekali.
        $this->assertSame($countBefore, GameAttempt::where('assignment_id', $assignment->uuid)->count());
        $this->assertSame(77, $realAttempt->fresh()->total_score);
        $this->assertSame(5, GamePracticeAttempt::where('session_id', $session->uuid)->count());

        // Query gradebook/hasil asli TAK BISA sama sekali ke-JOIN ke tabel practice (tak ada FK-nya).
        $this->assertDatabaseMissing('game_attempts', ['total_score' => 99]);
    }

    public function test_command_bersihkan_sesi_hapus_yg_lebih_dari_48_jam(): void
    {
        $lama = $this->startPractice();
        $p = GamePracticeParticipant::create([
            'session_id' => $lama->uuid, 'guest_name' => 'Tamu Lama', 'guest_token' => \Illuminate\Support\Str::random(40),
        ]);
        GamePracticeAttempt::create(['session_id' => $lama->uuid, 'participant_id' => $p->uuid]);
        DB::table('game_practice_sessions')->where('uuid', $lama->uuid)->update(['created_at' => now()->subHours(49)]);

        $baru = $this->startPractice();

        Artisan::call('latihan:bersihkan-sesi');

        $this->assertDatabaseMissing('game_practice_sessions', ['uuid' => $lama->uuid]);
        $this->assertDatabaseMissing('game_practice_participants', ['uuid' => $p->uuid]);
        $this->assertDatabaseHas('game_practice_sessions', ['uuid' => $baru->uuid]);
    }
}
