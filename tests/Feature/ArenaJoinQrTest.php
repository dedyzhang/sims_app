<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameQuiz;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Support\ArenaJoinQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ArenaJoinQrTest extends TestCase
{
    use RefreshDatabase;

    private Classroom $classroom;
    private GameQuiz $quiz;
    private User $guruUser;
    private User $siswaUser;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test']);
        Setting::set('modul_arena_belajar', '1');

        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'IPA', 'ringkasan' => 'IPA', 'kkm' => 75]);

        $this->guruUser = User::create([
            'username' => 'guru_arena_qr',
            'password' => Hash::make('x'),
            'access' => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $this->guruUser->uuid,
            'nama' => 'Guru Arena QR',
            'nik' => '8801',
            'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);

        $this->classroom = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title' => 'IPA 8A Arena QR',
            'status' => 'published',
            'class_code' => 'ARQR8A',
            'created_by' => $this->guruUser->uuid,
            'cover_color' => '#12345b',
        ]);

        $this->siswaUser = User::create([
            'username' => 'siswa_arena_qr',
            'password' => Hash::make('x'),
            'access' => 'siswa',
        ]);
        Siswa::create([
            'id_login' => $this->siswaUser->uuid,
            'id_kelas' => $kelas->uuid,
            'nama' => 'Siswa Arena QR',
            'nis' => '88001',
            'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);
        ClassroomMember::create([
            'classroom_id' => $this->classroom->uuid,
            'user_id' => $this->siswaUser->uuid,
            'role_in_class' => 'siswa',
            'joined_at' => now(),
        ]);

        $this->quiz = GameQuiz::create([
            'classroom_id' => $this->classroom->uuid,
            'title' => 'Kuis QR Test',
            'status' => 'published',
            'play_mode' => 'bebas',
            'scoring_mode' => 'standard',
            'max_score' => 100,
            'access_token' => 'PLAY',
            'created_by' => $this->guruUser->uuid,
        ]);
    }

    public function test_arena_join_qr_helper_builds_solo_and_live_urls(): void
    {
        $solo = ArenaJoinQr::soloJoinUrl($this->classroom, $this->quiz);
        $this->assertStringContainsString('join=solo', $solo);
        $this->assertStringContainsString('t=PLAY', $solo);

        $live = ArenaJoinQr::liveJoinUrl($this->classroom, $this->quiz);
        $this->assertStringContainsString('/live', $live);
        $this->assertStringContainsString('join=live', $live);
        $this->assertStringContainsString('t=PLAY', $live);

        $svg = ArenaJoinQr::svg('https://example.test/join');
        $this->assertStringContainsString('<svg', $svg);

        $this->assertSame('SIMS-ARENA:SOLO:PLAY', ArenaJoinQr::soloBarcodePayload($this->quiz));
        $this->assertSame('SIMS-ARENA:LIVE:PLAY', ArenaJoinQr::liveBarcodePayload($this->quiz));
    }

    public function test_guru_melihat_qr_gabung_di_halaman_quiz(): void
    {
        $this->actingAs($this->guruUser)
            ->get(route('classroom.arena.show', [$this->classroom, $this->quiz]))
            ->assertOk()
            ->assertSee('QR gabung siswa', false)
            ->assertSee('SIMS-ARENA:LIVE:PLAY', false)
            ->assertSee('arena-join-barcode', false)
            ->assertSee('<svg', false)
            ->assertSee('PLAY', false);
    }

    public function test_siswa_melihat_tombol_pindai_qr(): void
    {
        $this->actingAs($this->siswaUser)
            ->get(route('classroom.arena.show', [$this->classroom, $this->quiz]))
            ->assertOk()
            ->assertSee('Pindai QR dari guru', false)
            ->assertSee('arenaSoloJoin', false)
            ->assertSee('parseArenaJoinScan', false);
    }

    public function test_deep_link_solo_token_membuka_modal_token(): void
    {
        $url = ArenaJoinQr::soloJoinUrl($this->classroom, $this->quiz);

        $this->actingAs($this->siswaUser)
            ->get($url)
            ->assertOk()
            ->assertSee('prefillToken:', false)
            ->assertSee('PLAY', false)
            ->assertSee('autoOpen:', false);
    }

    public function test_deep_link_live_token_membuka_gate_live(): void
    {
        $url = ArenaJoinQr::liveJoinUrl($this->classroom, $this->quiz);

        $this->actingAs($this->siswaUser)
            ->get($url)
            ->assertOk()
            ->assertDontSee('Token Live Arena', false);
    }

    public function test_siswa_tanpa_token_ditolak_polling_live(): void
    {
        $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));

        $this->actingAs($this->siswaUser)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]))
            ->assertForbidden()
            ->assertJson(['requires_token' => true]);

        $this->actingAs($this->siswaUser)
            ->getJson(route('classroom.arena.live.leaderboard', [$this->classroom, $this->quiz]))
            ->assertForbidden()
            ->assertJson(['requires_token' => true]);
    }

    public function test_siswa_dengan_token_bisa_polling_live(): void
    {
        $this->actingAs($this->guruUser)
            ->post(route('classroom.arena.live.start', [$this->classroom, $this->quiz]));

        $this->actingAs($this->siswaUser)
            ->post(route('classroom.arena.join-token', [$this->classroom, $this->quiz]), [
                'join_token' => 'PLAY',
                'redirect' => route('classroom.arena.live', [$this->classroom, $this->quiz]),
            ])
            ->assertRedirect();

        $this->actingAs($this->siswaUser)
            ->getJson(route('classroom.arena.live.state', [$this->classroom, $this->quiz]))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_host_live_melihat_qr_gabung_di_lobi(): void
    {
        $this->actingAs($this->guruUser)
            ->get(route('classroom.arena.live', [$this->classroom, $this->quiz]))
            ->assertOk()
            ->assertSee('QR &amp; barcode gabung siswa', false)
            ->assertSee('SIMS-ARENA:LIVE:PLAY', false)
            ->assertSee('arena-join-barcode', false)
            ->assertSee('<svg', false);
    }
}
