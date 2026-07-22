<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameQuestion;
use App\Models\GameQuiz;
use App\Models\GameQuizAssignment;
use App\Models\GameTeam;
use App\Models\GameTeamMember;
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

class GameTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $guruUser;
    protected User $siswaUser;
    protected Classroom $classroom;
    protected GameQuiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);

        $this->guruUser = User::create(['username' => 'guru_tpl', 'password' => Hash::make('x'), 'access' => 'guru']);
        $guru = Guru::create([
            'id_login' => $this->guruUser->uuid, 'nama' => 'G', 'nik' => '9', 'jk' => 'L', 'face_descriptor' => [1],
        ]);
        $sem = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 9, 'kelas' => 'A']);
        $mapel = Pelajaran::create(['nama' => 'BI', 'ringkasan' => 'BI', 'kkm' => 75]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_kelas' => $kelas->uuid, 'id_pelajaran' => $mapel->uuid]);

        $this->classroom = Classroom::create([
            'id_semester' => $sem->id, 'id_kelas' => $kelas->uuid, 'id_pelajaran' => $mapel->uuid,
            'title' => 'BI 9A', 'status' => 'published', 'class_code' => 'TPL01',
            'created_by' => $this->guruUser->uuid, 'cover_color' => '#000',
        ]);

        $this->siswaUser = User::create(['username' => 'siswa_tpl', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create([
            'id_login' => $this->siswaUser->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'S', 'nis' => '1', 'jk' => 'L',
            'face_descriptor' => [1],
        ]);
        ClassroomMember::create([
            'classroom_id' => $this->classroom->uuid, 'user_id' => $this->siswaUser->uuid,
            'role_in_class' => 'siswa', 'joined_at' => now(),
        ]);

        $this->quiz = GameQuiz::create([
            'classroom_id' => $this->classroom->uuid, 'created_by' => $this->guruUser->uuid,
            'title' => 'Bank Soal', 'status' => 'published', 'max_score' => 100, 'template' => 'quiz',
            'is_locked' => true, 'access_token' => 'SYNC',
        ]);
        GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'short_answer', 'question_text' => 'Hewan berkaki 4?',
            'points' => 1, 'sort_order' => 0, 'meta' => ['answers' => ['SAPI']],
        ]);
        GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'short_answer', 'question_text' => 'Warna daun?',
            'points' => 1, 'sort_order' => 1, 'meta' => ['answers' => ['HIJAU']],
        ]);
        GameQuestion::create([
            'quiz_id' => $this->quiz->uuid, 'type' => 'short_answer', 'question_text' => 'Langit?',
            'points' => 1, 'sort_order' => 2, 'meta' => ['answers' => ['BIRU']],
        ]);
        GameQuizAssignment::create([
            'quiz_id' => $this->quiz->uuid, 'classroom_id' => $this->classroom->uuid, 'status' => 'open',
        ]);
    }

    public function test_template_switch_does_not_duplicate_questions(): void
    {
        $before = $this->quiz->questions()->count();
        $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.template', [$this->classroom, $this->quiz]), ['template' => 'flashcard'])
            ->assertRedirect();

        $this->quiz->refresh();
        $this->assertSame('flashcard', $this->quiz->template);
        $this->assertSame($before, $this->quiz->questions()->count());
    }

    public function test_guru_can_set_and_open_ular_tangga_template(): void
    {
        $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.template', [$this->classroom, $this->quiz]), ['template' => 'ular_tangga'])
            ->assertRedirect();

        $this->quiz->refresh();
        $this->assertSame('ular_tangga', $this->quiz->template);

        $this->actingAs($this->guruUser)
            ->get(route('classroom.arena.template.play', [$this->classroom, $this->quiz]))
            ->assertOk()
            ->assertSee('Ular tangga', false)
            ->assertSee('Lempar dadu', false);
    }

    public function test_siswa_cannot_open_ular_tangga_template_with_keys(): void
    {
        $this->quiz->update(['template' => 'ular_tangga']);

        $this->actingAs($this->siswaUser)
            ->get(route('classroom.arena.template.play', [$this->classroom, $this->quiz]))
            ->assertStatus(403);
    }

    public function test_pdf_ok_for_guru_and_key_forbidden_for_siswa(): void
    {
        $this->actingAs($this->guruUser)
            ->get(route('classroom.arena.pdf', [$this->classroom, $this->quiz, 'kunci' => 1]))
            ->assertOk();

        $this->actingAs($this->siswaUser)
            ->get(route('classroom.arena.pdf', [$this->classroom, $this->quiz, 'kunci' => 1]))
            ->assertStatus(403);

        $this->actingAs($this->siswaUser)
            ->get(route('classroom.arena.pdf', [$this->classroom, $this->quiz]))
            ->assertOk();
    }

    public function test_team_scoring_aggregate(): void
    {
        $team = GameTeam::create([
            'quiz_id' => $this->quiz->uuid, 'classroom_id' => $this->classroom->uuid,
            'name' => 'Merah', 'sort_order' => 0,
        ]);
        GameTeamMember::create(['team_id' => $team->uuid, 'user_id' => $this->siswaUser->uuid]);

        $this->actingAs($this->guruUser)
            ->getJson(route('classroom.arena.teams.board', [$this->classroom, $this->quiz]))
            ->assertOk()
            ->assertJsonPath('teams.0.team', 'Merah');
    }

    public function test_offline_sync_idempotent(): void
    {
        $q = $this->quiz->questions()->first();
        $this->actingAs($this->siswaUser)
            ->withSession(['arena_solo_unlock.'.$this->quiz->uuid => 'SYNC'])
            ->postJson(route('classroom.arena.sync', [$this->classroom, $this->quiz]), [
                'answers' => [[
                    'question_id' => $q->uuid,
                    'answer_text' => 'SAPI',
                ]],
                'submit' => false,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        // sync lagi
        $this->actingAs($this->siswaUser)
            ->withSession(['arena_solo_unlock.'.$this->quiz->uuid => 'SYNC'])
            ->postJson(route('classroom.arena.sync', [$this->classroom, $this->quiz]), [
                'answers' => [[
                    'question_id' => $q->uuid,
                    'answer_text' => 'SAPI',
                ]],
                'submit' => true,
            ])
            ->assertOk();
    }
}
