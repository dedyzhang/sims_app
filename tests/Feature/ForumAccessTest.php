<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\Semester;
use Database\Seeders\ForumPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForumAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed settings
        \App\Models\Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        \App\Models\Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);

        // Run the ForumPermissionSeeder
        $this->seed(ForumPermissionSeeder::class);
    }

    public function test_student_cannot_access_forum_topic_creation_pages_or_endpoints()
    {
        // 1. Create a student user
        $student = User::create([
            'username' => 'student_user',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);

        // 2. Try to access the create topic page
        $responseGet = $this->actingAs($student)->get('/forum/buat');
        $responseGet->assertStatus(403);

        // 3. Try to submit a post request to create a topic
        $responsePost = $this->actingAs($student)->post('/forum', [
            'title' => 'Topik Siswa',
            'body' => 'Isi topik siswa',
            'category' => 'umum',
            'audience' => 'siswa_guru',
        ]);
        $responsePost->assertStatus(403);
    }

    public function test_teacher_can_access_forum_topic_creation_pages()
    {
        // 1. Create a teacher user with a guru profile
        $teacher = User::create([
            'username' => 'teacher_user',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $teacher->uuid,
            'nama' => 'Teacher One',
            'nik' => '1112223334',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        // Create prerequisites for create page (classes & subjects)
        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'ringkasan' => 'MTK', 'kkm' => 75]);

        // 2. Teacher visits create topic page
        $responseGet = $this->actingAs($teacher)->get('/forum/buat');
        $responseGet->assertStatus(200);
        $responseGet->assertSee('Buat Topik Diskusi');
    }
}
