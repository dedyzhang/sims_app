<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\MissionAttempt;
use App\Models\MissionBadge;
use App\Models\MissionStep;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MissionProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_and_syncs_a_student_progress_profile(): void
    {
        [$mission, $user] = $this->createFixture();

        MissionAttempt::create([
            'mission_id' => $mission->uuid,
            'user_id' => $user->uuid,
            'status' => 'completed',
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
            'score' => 94,
            'duration_seconds' => 1200,
            'result_meta' => [],
        ]);

        MissionBadge::factory()->firstMission()->create();

        $response = $this->actingAs($user)->getJson(route('jagat-misi.api.progress'));

        $response->assertOk()
            ->assertJsonPath('data.profile.summary.xp', 94)
            ->assertJsonPath('data.profile.summary.missions_completed', 1);
    }

    public function test_it_returns_school_leaderboard(): void
    {
        [$mission, $user] = $this->createFixture();

        MissionAttempt::create([
            'mission_id' => $mission->uuid,
            'user_id' => $user->uuid,
            'status' => 'completed',
            'completed_at' => now(),
            'score' => 80,
            'duration_seconds' => 600,
            'result_meta' => [],
        ]);

        $response = $this->actingAs($user)->getJson(route('jagat-misi.api.leaderboard'));

        $response->assertOk()
            ->assertJsonPath('data.leaderboard.count', 1)
            ->assertJsonPath('data.leaderboard.entries.0.xp', 80);
    }

    /** Bug nyata terukur: halaman /jagat-misi/progres sempat memicu 1637 query — leaderboard()
     *  memanggil summaryFor() (3 query) DAN User::displayName() (2 query lazy-load guru+siswa)
     *  per SISWA, bukan sekali secara bulk. Test ini mengunci PERILAKU jumlah query supaya tak
     *  diam-diam N+1 lagi kalau leaderboard() diubah lagi nanti — jumlah query harus TETAP SAMA
     *  brp pun jumlah siswanya (bukti nyata bahwa query-nya bulk, bukan per-baris). */
    public function test_leaderboard_jumlah_query_tidak_bertambah_seiring_jumlah_siswa(): void
    {
        [$mission] = $this->createFixture();

        $countQueries = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(route('jagat-misi.api.leaderboard'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->actingAs(User::where('access', 'siswa')->first());
        // Panggilan "pemanasan" — request PERTAMA sbg user ini memicu efek samping middleware
        // auth (update last_seen_at + 1 cek relasi siswa) yg TAK ADA HUBUNGANNYA dgn efisiensi
        // leaderboard() itu sendiri; itu cuma terjadi sekali per user, jadi harus disingkirkan
        // dari kedua sisi perbandingan supaya benar2 apple-to-apple.
        $countQueries();
        $with1Student = $countQueries();

        // Tambah 9 siswa lagi (leaderboard_visible + 1 attempt masing2) — total 10.
        for ($i = 0; $i < 9; $i++) {
            $u = User::create([
                'username' => 'siswa_lb_' . $i,
                'password' => Hash::make('password'),
                'access' => 'siswa',
                'leaderboard_visible' => true,
            ]);
            Siswa::create([
                'id_login' => $u->uuid,
                'nama' => 'Siswa LB ' . $i,
                'nis' => '9100' . $i,
                'jk' => 'L',
                'face_descriptor' => [0.1, 0.2],
            ]);
            MissionAttempt::create([
                'mission_id' => $mission->uuid,
                'user_id' => $u->uuid,
                'status' => 'completed',
                'completed_at' => now(),
                'score' => 50 + $i,
                'duration_seconds' => 300,
                'result_meta' => [],
            ]);
        }

        $with10Students = $countQueries();

        $this->assertSame(
            $with1Student,
            $with10Students,
            'Jumlah query leaderboard harus SAMA persis walau jumlah siswa naik dari 1 ke 10 — kalau naik, berarti N+1 balik lagi.'
        );
    }

    public function test_it_can_toggle_leaderboard_visibility(): void
    {
        [, $user] = $this->createFixture();

        $response = $this->actingAs($user)->patchJson(route('jagat-misi.api.leaderboard.visibility'), [
            'leaderboard_visible' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.leaderboard_visible', false);

        $this->assertFalse((bool) $user->fresh()->leaderboard_visible);
    }

    private function createFixture(): array
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);

        $user = User::create([
            'username' => 'siswa_prog',
            'password' => Hash::make('password'),
            'access' => 'siswa',
            'leaderboard_visible' => true,
        ]);
        Siswa::create([
            'id_login' => $user->uuid,
            'nama' => 'Siswa Prog',
            'nis' => '9002',
            'jk' => 'P',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $mission = Mission::factory()->nalar()->create(['is_published' => true]);
        MissionStep::factory()->narrative()->create(['mission_id' => $mission->uuid]);

        return [$mission, $user];
    }
}
