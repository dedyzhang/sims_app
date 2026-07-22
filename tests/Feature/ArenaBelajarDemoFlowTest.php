<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameAttempt;
use App\Models\GameQuiz;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\ArenaBelajarDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ArenaBelajarDemoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_quizzes_appear_in_hub_and_can_be_scored_end_to_end(): void
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);

        $guruUser = User::create([
            'username' => 'guru_demo_arena',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $guruUser->uuid,
            'nama' => 'Guru Demo Arena',
            'nik' => '4101',
            'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'ringkasan' => 'MTK', 'kkm' => 75]);
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);

        $classroom = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title' => 'Matematika 7A Demo Arena',
            'status' => 'published',
            'class_code' => 'DEMO-AR',
            'created_by' => $guruUser->uuid,
            'cover_color' => '#12345b',
        ]);

        $siswaUser = User::create([
            'username' => 'siswa_demo_arena',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
        Siswa::create([
            'id_login' => $siswaUser->uuid,
            'id_kelas' => $kelas->uuid,
            'nama' => 'Siswa Demo Arena',
            'nis' => '7101',
            'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);
        ClassroomMember::create([
            'classroom_id' => $classroom->uuid,
            'user_id' => $siswaUser->uuid,
            'role_in_class' => 'siswa',
            'joined_at' => now(),
        ]);

        $this->seed(ArenaBelajarDemoSeeder::class);

        $math = GameQuiz::where('title', '[DEMO] Matematika Dasar — Arena')->firstOrFail();
        $ipa = GameQuiz::where('title', '[DEMO] IPA — Rantai Makanan')->firstOrFail();

        // Kontrak baru Arena: main solo selalu butuh token dari guru — set token dulu
        // lalu kirim solo_token saat start (seperti siswa memasukkan token di UI).
        $math->update(['access_token' => 'DEMO']);

        $this->assertGreaterThanOrEqual(7, $math->questions()->count());
        $this->assertGreaterThanOrEqual(5, $ipa->questions()->count());

        $hub = $this->actingAs($siswaUser)->get(route('classroom.arena.index', $classroom));
        $hub->assertOk()
            ->assertSee('Arena Belajar')
            ->assertSee('[DEMO] Matematika Dasar — Arena')
            ->assertSee('[DEMO] IPA — Rantai Makanan');

        $this->actingAs($siswaUser)
            ->post(route('classroom.arena.start', [$classroom, $math]), ['solo_token' => 'DEMO'])
            ->assertRedirect();

        $attempt = GameAttempt::where('student_id', $siswaUser->uuid)->firstOrFail();
        $math->load('questions.options');

        $answers = [];
        foreach ($math->questions as $q) {
            if (in_array($q->type, ['mcq', 'true_false'], true)) {
                $opt = $q->options->firstWhere('is_correct', true);
                $answers[] = [
                    'question_id' => $q->uuid,
                    'selected_option_id' => $opt->uuid,
                ];
            } elseif ($q->type === 'short_answer') {
                $answers[] = [
                    'question_id' => $q->uuid,
                    'answer_text' => (string) ($q->meta['answers'][0] ?? ''),
                ];
            } elseif ($q->type === 'match') {
                $map = [];
                foreach ($q->meta['pairs'] ?? [] as $pair) {
                    $map[$pair['left']] = $pair['right'];
                }
                $answers[] = [
                    'question_id' => $q->uuid,
                    'answer_text' => json_encode($map, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        $submit = $this->actingAs($siswaUser)->post(
            route('classroom.arena.submit', [$classroom, $math, $attempt]),
            ['answers' => $answers, 'duration_ms' => 45000]
        );

        $submit->assertRedirect(route('classroom.arena.result', [$classroom, $math, $attempt]));
        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame(100, $attempt->total_score);
        $this->assertSame($math->questions()->count(), $attempt->correct_count);
    }

    public function test_demo_ipa_quiz_accepts_fuzzy_short_answer(): void
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);

        $guruUser = User::create([
            'username' => 'guru_demo_ipa',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $guruUser->uuid,
            'nama' => 'Guru IPA',
            'nik' => '4102',
            'jk' => 'P',
            'face_descriptor' => [0.1],
        ]);
        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);
        $pelajaran = Pelajaran::create(['nama' => 'IPA', 'ringkasan' => 'IPA', 'kkm' => 75]);
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);
        $classroom = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title' => 'IPA 8B Demo',
            'status' => 'published',
            'class_code' => 'DEMO-IP',
            'created_by' => $guruUser->uuid,
            'cover_color' => '#00a99d',
        ]);
        $siswaUser = User::create([
            'username' => 'siswa_demo_ipa',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
        Siswa::create([
            'id_login' => $siswaUser->uuid,
            'id_kelas' => $kelas->uuid,
            'nama' => 'Siswa IPA',
            'nis' => '7102',
            'jk' => 'P',
            'face_descriptor' => [0.1],
        ]);
        ClassroomMember::create([
            'classroom_id' => $classroom->uuid,
            'user_id' => $siswaUser->uuid,
            'role_in_class' => 'siswa',
            'joined_at' => now(),
        ]);

        $this->seed(ArenaBelajarDemoSeeder::class);

        $ipa = GameQuiz::where('title', '[DEMO] IPA — Rantai Makanan')->firstOrFail()->load('questions.options');

        // Kontrak baru Arena: solo wajib token — set token guru lalu kirim saat start.
        $ipa->update(['access_token' => 'DEMO']);

        $this->actingAs($siswaUser)->post(route('classroom.arena.start', [$classroom, $ipa]), ['solo_token' => 'DEMO']);
        $attempt = GameAttempt::where('student_id', $siswaUser->uuid)->firstOrFail();

        $answers = [];
        foreach ($ipa->questions as $q) {
            if (in_array($q->type, ['mcq', 'true_false'], true)) {
                $opt = $q->options->firstWhere('is_correct', true);
                $answers[] = [
                    'question_id' => $q->uuid,
                    'selected_option_id' => $opt->uuid,
                ];
            } elseif ($q->type === 'short_answer') {
                // Alternatif yang diterima seeder (bukan jawaban pertama)
                $answers[] = [
                    'question_id' => $q->uuid,
                    'answer_text' => 'rumput',
                ];
            } elseif ($q->type === 'match') {
                $map = [];
                foreach ($q->meta['pairs'] ?? [] as $pair) {
                    $map[$pair['left']] = $pair['right'];
                }
                $answers[] = [
                    'question_id' => $q->uuid,
                    'answer_text' => json_encode($map, JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        $this->actingAs($siswaUser)
            ->post(route('classroom.arena.submit', [$classroom, $ipa, $attempt]), [
                'answers' => $answers,
                'duration_ms' => 30000,
            ])
            ->assertRedirect();

        $attempt->refresh();
        $this->assertSame(100, $attempt->total_score);
    }
}
