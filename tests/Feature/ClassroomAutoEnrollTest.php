<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GameQuestion;
use App\Models\GameQuestionOption;
use App\Models\GameQuiz;
use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresi: siswa yang dimasukkan ke sebuah kelas SETELAH ruang kelas utk kelas itu sudah
 * ada tidak pernah dapat baris classroom_members — ClassroomPolicy::isMember() gagal, siswa
 * kena 403 "This action is unauthorized" saat buka Ruang Kelas, dan tidak muncul utk join
 * Arena Belajar (GameQuizPolicy juga bergantung pada ClassroomPolicy::view()/isMember()).
 * Diperbaiki via ClassroomService::enrollStudentInKelasClassrooms(), dipanggil dari
 * SiswaController::store()/update() dan KelasController::saveRombel().
 */
class ClassroomAutoEnrollTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'enroll_admin'],
            ['password' => Hash::make('password'), 'access' => 'superadmin']
        );
    }

    /** Kelas + ruang kelas (Classroom) yg sudah terbit utk kelas itu, dibuat SEBELUM ada siswa. */
    private function kelasDenganRuangKelas(): array
    {
        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'ringkasan' => 'MTK', 'kkm' => 75]);

        $classroom = Classroom::create([
            'id_semester'  => $semester->id,
            'id_kelas'     => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title'        => 'Matematika 7A',
            'status'       => 'published',
            'class_code'   => 'ENROLL01',
            'created_by'   => $this->admin()->uuid,
        ]);

        return [$kelas, $classroom];
    }

    /** Kuis Arena Belajar yang sudah terbit ("live") — siap di-join siswa anggota kelas. */
    private function quizLive(Classroom $classroom): GameQuiz
    {
        $quiz = GameQuiz::create([
            'classroom_id'     => $classroom->uuid,
            'created_by'       => $classroom->created_by,
            'title'            => 'Kuis Arena',
            'mode'             => 'async',
            'scoring_mode'     => 'accuracy',
            'max_score'        => 100,
            'instant_feedback' => true,
            'status'           => 'published',
        ]);
        $q = GameQuestion::create([
            'quiz_id'       => $quiz->uuid, 'type' => 'mcq', 'question_text' => '1+1=?', 'points' => 1, 'sort_order' => 0,
        ]);
        GameQuestionOption::create(['question_id' => $q->uuid, 'option_text' => '2', 'is_correct' => true, 'sort_order' => 0]);
        GameQuestionOption::create(['question_id' => $q->uuid, 'option_text' => '3', 'is_correct' => false, 'sort_order' => 1]);

        return $quiz;
    }

    public function test_siswa_baru_langsung_jadi_anggota_ruang_kelas_yg_sudah_ada(): void
    {
        [$kelas, $classroom] = $this->kelasDenganRuangKelas();

        $response = $this->actingAs($this->admin())->post(route('siswa.store'), [
            'nama'     => 'Siswa Baru',
            'nis'      => 'ENR001',
            'jk'       => 'L',
            'id_kelas' => $kelas->uuid,
        ]);
        $response->assertRedirect(route('siswa.index'));

        $siswa = Siswa::where('nis', 'ENR001')->firstOrFail();
        $this->assertDatabaseHas('classroom_members', [
            'classroom_id' => $classroom->uuid,
            'user_id'      => $siswa->id_login,
        ]);

        // siswa.store bikin akun dgn must_change_password=true & tanpa wajah — set manual di
        // sini hanya supaya lolos gate password/EnsureFaceRegistered lain saat verifikasi akses
        // (gate2 itu bukan bagian dari fix ini).
        $siswa->update(['face_descriptor' => [0.1, 0.2]]);
        $siswa->user->update(['must_change_password' => false]);
        $this->actingAs($siswa->user)->get(route('classroom.show', $classroom))->assertOk();
    }

    public function test_siswa_pindah_kelas_via_edit_langsung_jadi_anggota_ruang_kelas_tujuan(): void
    {
        [$kelasTujuan, $classroom] = $this->kelasDenganRuangKelas();
        $kelasAsal = Kelas::create(['tingkat' => 7, 'kelas' => 'Z']);

        $user = User::create(['username' => 'siswa_pindah', 'password' => Hash::make('x'), 'access' => 'siswa']);
        $siswa = Siswa::create(['id_login' => $user->uuid, 'nama' => 'Siswa Pindah', 'nis' => 'ENR002', 'jk' => 'L', 'id_kelas' => $kelasAsal->uuid, 'face_descriptor' => [0.1, 0.2]]);

        $response = $this->actingAs($this->admin())->put(route('siswa.update', $siswa), [
            'nama'     => $siswa->nama,
            'nis'      => $siswa->nis,
            'jk'       => 'L',
            'id_kelas' => $kelasTujuan->uuid,
        ]);
        $response->assertRedirect(route('siswa.show', $siswa));

        $this->assertDatabaseHas('classroom_members', [
            'classroom_id' => $classroom->uuid,
            'user_id'      => $user->uuid,
        ]);
        $this->actingAs($user)->get(route('classroom.show', $classroom))->assertOk();
    }

    public function test_set_kelas_massal_langsung_daftarkan_siswa_ke_ruang_kelas_dan_arena_belajar(): void
    {
        [$kelas, $classroom] = $this->kelasDenganRuangKelas();
        $quiz = $this->quizLive($classroom);

        $user = User::create(['username' => 'siswa_belum_kelas', 'password' => Hash::make('x'), 'access' => 'siswa']);
        $siswa = Siswa::create(['id_login' => $user->uuid, 'nama' => 'Siswa Belum Kelas', 'nis' => 'ENR003', 'jk' => 'L', 'id_kelas' => null, 'face_descriptor' => [0.1, 0.2]]);

        $response = $this->actingAs($this->admin())->post(route('kelas.saveRombel', $kelas), [
            'siswa_ids' => [$siswa->uuid],
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('classroom_members', [
            'classroom_id' => $classroom->uuid,
            'user_id'      => $user->uuid,
        ]);

        // Bug 1: Ruang Kelas bisa dibuka (bukan 403).
        $this->actingAs($user)->get(route('classroom.show', $classroom))->assertOk();

        // Bug 2: Arena Belajar yg sudah live bisa di-join (bukan 403).
        $this->actingAs($user)
            ->post(route('classroom.arena.start', [$classroom, $quiz]))
            ->assertRedirect();
    }

    public function test_perintah_repair_membership_membetulkan_siswa_lama_yg_sudah_terlanjur_tidak_jadi_anggota(): void
    {
        [$kelas, $classroom] = $this->kelasDenganRuangKelas();

        // Simulasikan data YANG SUDAH TERLANJUR rusak (siswa masuk kelas lewat cara apa pun
        // sebelum perbaikan ini dipasang) — insert langsung ke model, TANPA lewat controller,
        // supaya ClassroomMember memang belum ada.
        $user = User::create(['username' => 'siswa_lama_rusak', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'nama' => 'Siswa Lama', 'nis' => 'ENR004', 'jk' => 'L', 'id_kelas' => $kelas->uuid, 'face_descriptor' => [0.1, 0.2]]);

        $this->assertDatabaseMissing('classroom_members', ['classroom_id' => $classroom->uuid, 'user_id' => $user->uuid]);
        $this->actingAs($user)->get(route('classroom.show', $classroom))->assertForbidden();

        Artisan::call('classroom:repair-membership');

        $this->assertDatabaseHas('classroom_members', ['classroom_id' => $classroom->uuid, 'user_id' => $user->uuid]);
        $this->actingAs($user)->get(route('classroom.show', $classroom))->assertOk();
    }

    public function test_perintah_repair_membership_idempotent_tidak_duplikat(): void
    {
        [$kelas, $classroom] = $this->kelasDenganRuangKelas();
        $user = User::create(['username' => 'siswa_idempotent', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'nama' => 'Siswa Idempotent', 'nis' => 'ENR005', 'jk' => 'L', 'id_kelas' => $kelas->uuid]);

        Artisan::call('classroom:repair-membership');
        Artisan::call('classroom:repair-membership');

        $this->assertSame(1, ClassroomMember::where('classroom_id', $classroom->uuid)->where('user_id', $user->uuid)->count());
    }

    public function test_siswa_kelas_lain_tetap_tidak_bisa_akses_ruang_kelas(): void
    {
        [$kelas, $classroom] = $this->kelasDenganRuangKelas();
        $kelasLain = Kelas::create(['tingkat' => 8, 'kelas' => 'X']);

        $user = User::create(['username' => 'siswa_kelas_lain', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'nama' => 'Siswa Kelas Lain', 'nis' => 'ENR006', 'jk' => 'L', 'id_kelas' => $kelasLain->uuid, 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($this->admin())->post(route('siswa.store'), [
            'nama' => 'Siswa Trigger', 'nis' => 'ENR007', 'jk' => 'L', 'id_kelas' => $kelasLain->uuid,
        ]);

        $this->assertDatabaseMissing('classroom_members', ['classroom_id' => $classroom->uuid, 'user_id' => $user->uuid]);
        $this->actingAs($user)->get(route('classroom.show', $classroom))->assertForbidden();
    }
}
