<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Mission;
use App\Models\MissionActivityLog;
use App\Models\MissionAssignment;
use App\Models\MissionAttempt;
use App\Models\MissionStep;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MissionNalarTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_a_mission_bundle_for_authorized_user(): void
    {
        [$mission, $user] = $this->createMissionFixture();

        $response = $this->actingAs($user)->getJson(route('jagat-misi.api.show', $mission));

        $response->assertOk()
            ->assertJsonPath('data.mission.title', 'Jejak Pagi di Hutan')
            ->assertJsonCount(3, 'data.steps');
    }

    public function test_api_does_not_expose_answer_keys(): void
    {
        [$mission, $user] = $this->createMissionFixture();

        $response = $this->actingAs($user)->getJson(route('jagat-misi.api.show', $mission));

        $response->assertOk()
            ->assertJsonMissingPath('data.steps.0.payload.expected_path')
            ->assertJsonMissingPath('data.steps.1.payload.rounds.0.ideal_choice')
            ->assertJsonMissingPath('data.steps.1.payload.thresholds')
            ->assertJsonMissingPath('data.steps.2.payload.correct_order');
    }

    public function test_it_scores_and_persists_attempts_in_a_transaction(): void
    {
        [$mission, , $assignment, $guru] = $this->createMissionFixture(withAssignment: true);

        $response = $this->actingAs($guru)->postJson(route('jagat-misi.api.attempts', $mission), [
            'assignment_id' => $assignment->uuid,
            'responses' => [
                'interactive_narrative' => [
                    'path' => ['Periksa sumber air', 'Bersihkan aliran', 'Selesaikan laporan'],
                    'final_node' => 'finish',
                ],
                'strategic_decision' => [
                    'choices' => ['Bersihkan drainase', 'Buka forum warga', 'Buka jalur tambahan'],
                ],
                'puzzle_sequencing' => [
                    'order' => ['survey', 'materials', 'foundation', 'bridge', 'test'],
                ],
            ],
            'duration_seconds' => 123,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.attempt.score', 100)
            ->assertJsonPath('data.attempt.result_meta.points_awarded', 100);

        $this->assertDatabaseCount('mission_attempts', 1);
        $this->assertDatabaseCount('mission_attempt_responses', 3);
        $this->assertDatabaseCount('mission_activity_logs', 1);

        $attempt = MissionAttempt::query()->first();
        $this->assertSame('completed', $attempt->status);
        $this->assertSame(100, $attempt->score);

        $log = MissionActivityLog::query()->first();
        $this->assertSame('mission_attempt.completed', $log->action);
    }

    public function test_siswa_cannot_submit_without_classroom_assignment(): void
    {
        [$mission, $siswa] = $this->createMissionFixture();

        $this->actingAs($siswa)->postJson(route('jagat-misi.api.attempts', $mission), [
            'responses' => [
                'interactive_narrative' => [
                    'path' => ['Periksa sumber air'],
                    'final_node' => 'finish',
                ],
                'strategic_decision' => [
                    'choices' => ['Bersihkan drainase'],
                ],
                'puzzle_sequencing' => [
                    'order' => ['survey'],
                ],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('mission_attempts', 0);
    }

    public function test_catalog_page_renders_published_missions(): void
    {
        [$mission, $user] = $this->createMissionFixture();

        $response = $this->actingAs($user)->get(route('jagat-misi.index'));

        $response->assertOk()
            ->assertSee('Arena Belajar')
            ->assertSee('Katalog Misi')
            ->assertSee($mission->title);
    }

    public function test_katalog_menyembunyikan_misi_tanpa_langkah(): void
    {
        [$mission, $user] = $this->createMissionFixture();

        $empty = Mission::factory()->recallQuiz()->create([
            'slug' => 'shell-tanpa-langkah-'.uniqid(),
            'title' => 'Shell Tanpa Langkah',
            'is_published' => true,
            'status' => 'published',
            'visible_to_teachers' => true,
        ]);

        $this->actingAs($user)
            ->get(route('jagat-misi.index'))
            ->assertOk()
            ->assertSee($mission->title)
            ->assertDontSee('Shell Tanpa Langkah');

        $this->actingAs($user)
            ->get(route('jagat-misi.play', $empty))
            ->assertNotFound();
    }

    private function createMissionFixture(bool $withAssignment = false): array
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);

        $guruUser = User::create([
            'username' => 'guru_jm_nalar',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $guruUser->uuid,
            'nama' => 'Guru Nalar',
            'nik' => '3101',
            'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);

        $siswaUser = User::create([
            'username' => 'siswa_jm',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 5, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'IPA', 'ringkasan' => 'IPA', 'kkm' => 75]);
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);

        Siswa::create([
            'id_login' => $siswaUser->uuid,
            'id_kelas' => $kelas->uuid,
            'nama' => 'Siswa JM',
            'nis' => '9001',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $mission = Mission::factory()->nalar()->create([
            'is_published' => true,
        ]);

        MissionStep::factory()->narrative()->create(['mission_id' => $mission->uuid]);
        MissionStep::factory()->decision()->create(['mission_id' => $mission->uuid]);
        MissionStep::factory()->puzzle()->create(['mission_id' => $mission->uuid]);

        $assignment = null;
        if ($withAssignment) {
            $classroom = Classroom::create([
                'id_semester' => $semester->id,
                'id_kelas' => $kelas->uuid,
                'id_pelajaran' => $pelajaran->uuid,
                'title' => 'IPA 5A',
                'status' => 'published',
                'class_code' => 'NAL001',
                'created_by' => $guruUser->uuid,
                'cover_color' => '#111',
            ]);
            ClassroomMember::create([
                'classroom_id' => $classroom->uuid,
                'user_id' => $siswaUser->uuid,
                'role_in_class' => 'siswa',
                'joined_at' => now(),
            ]);
            $assignment = MissionAssignment::create([
                'mission_id' => $mission->uuid,
                'classroom_id' => $classroom->uuid,
                'assigned_by' => $guruUser->uuid,
                'status' => 'open',
            ]);
        }

        if ($withAssignment) {
            return [$mission, $siswaUser, $assignment, $guruUser];
        }

        return [$mission, $siswaUser];
    }
}
