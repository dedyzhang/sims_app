<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Classroom;
use App\Models\Semester;
use App\Models\Pelajaran;
use App\Models\Ngajar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClassroomAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed settings because dashboard/layouts might look for settings
        \App\Models\Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        \App\Models\Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);
    }

    public function test_kesiswaan_user_without_guru_profile_sees_no_classrooms()
    {
        // 1. Create a user with 'kesiswaan' access (no Guru record)
        $user = User::create([
            'username' => 'eka_kesiswaan',
            'password' => Hash::make('password'),
            'access' => 'kesiswaan',
        ]);

        // 2. Create some classes
        Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        Kelas::create(['tingkat' => 7, 'kelas' => 'B']);

        // 3. Act as the user and request the classrooms index page
        $response = $this->actingAs($user)->get(route('classroom.index'));

        // 4. Assert success and that they see the empty state
        $response->assertStatus(200);
        $response->assertSee('Belum Ada Kelas');
        $response->assertDontSee('Kelas 7A');
        $response->assertDontSee('Kelas 7B');
    }

    public function test_kesiswaan_user_with_guru_profile_only_sees_assigned_classrooms()
    {
        // 1. Create a user with 'kesiswaan' access
        $user = User::create([
            'username' => 'eka_kesiswaan',
            'password' => Hash::make('password'),
            'access' => 'kesiswaan',
        ]);

        // 2. Create Guru record with face descriptor set to bypass face registered check
        $guru = Guru::create([
            'id_login' => $user->uuid,
            'nama' => 'Eka Kurniati',
            'nik' => '1234567890',
            'jk' => 'P',
            'face_descriptor' => [0.1, 0.2],
        ]);

        // 3. Create two classes
        $kelas1 = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $kelas2 = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);

        // 4. Assign Eka to teach Kelas 7A
        $pelajaran = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas1->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);

        // 5. Act as the user and request the classrooms index page
        $response = $this->actingAs($user)->get(route('classroom.index'));

        // 6. Assert Eka only sees Kelas 7A, and not Kelas 7B
        $response->assertStatus(200);
        $response->assertSee('Kelas 7A');
        $response->assertDontSee('Kelas 7B');
    }

    public function test_kesiswaan_user_only_sees_assigned_subjects_in_class()
    {
        // 1. Create a user with 'kesiswaan' access
        $user = User::create([
            'username' => 'eka_kesiswaan',
            'password' => Hash::make('password'),
            'access' => 'kesiswaan',
        ]);

        // 2. Create Guru record with face descriptor
        $guru = Guru::create([
            'id_login' => $user->uuid,
            'nama' => 'Eka Kurniati',
            'nik' => '1234567890',
            'jk' => 'P',
            'face_descriptor' => [0.1, 0.2],
        ]);

        // 3. Create another teacher
        $otherUser = User::create([
            'username' => 'other_teacher',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $otherGuru = Guru::create([
            'id_login' => $otherUser->uuid,
            'nama' => 'Other Teacher',
            'nik' => '0987654321',
            'jk' => 'L',
        ]);

        // 4. Create class and subjects
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pkn = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);
        $math = Pelajaran::create(['nama' => 'Matematika', 'ringkasan' => 'MTK', 'kkm' => 70]);

        // 5. Assign PKN to Eka, Math to other teacher
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pkn->uuid,
        ]);
        Ngajar::create([
            'id_guru' => $otherGuru->uuid,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $math->uuid,
        ]);

        // 6. Act as Eka and request the class details page
        $response = $this->actingAs($user)->get(route('classroom.kelas', $kelas));

        // 7. Assert Eka sees PPKN but does NOT see Matematika
        $response->assertStatus(200);
        $response->assertSee('Pendidikan Pancasila');
        $response->assertDontSee('Matematika');
    }

    public function test_kesiswaan_user_cannot_access_unassigned_subject_room()
    {
        // 1. Create a user with 'kesiswaan' access
        $user = User::create([
            'username' => 'eka_kesiswaan',
            'password' => Hash::make('password'),
            'access' => 'kesiswaan',
        ]);

        // 2. Create Guru record with face descriptor
        $guru = Guru::create([
            'id_login' => $user->uuid,
            'nama' => 'Eka Kurniati',
            'nik' => '1234567890',
            'jk' => 'P',
            'face_descriptor' => [0.1, 0.2],
        ]);

        // 3. Create class and subjects
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pkn = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);
        $math = Pelajaran::create(['nama' => 'Matematika', 'ringkasan' => 'MTK', 'kkm' => 70]);

        // 4. Assign PKN to Eka (she does not teach Math)
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pkn->uuid,
        ]);

        // 5. Act as Eka and try to access Math subject room in Kelas 7A
        $response = $this->actingAs($user)->get(route('classroom.subject', [$kelas, $math]));

        // 6. Assert forbidden (403)
        $response->assertStatus(403);
    }

    public function test_kesiswaan_user_can_access_assigned_classroom_details()
    {
        // 1. Create a user with 'kesiswaan' access
        $user = User::create([
            'username' => 'eka_kesiswaan',
            'password' => Hash::make('password'),
            'access' => 'kesiswaan',
        ]);

        // 2. Create Guru record with face descriptor
        $guru = Guru::create([
            'id_login' => $user->uuid,
            'nama' => 'Eka Kurniati',
            'nik' => '1234567890',
            'jk' => 'P',
            'face_descriptor' => [0.1, 0.2],
        ]);

        // 3. Create semester, kelas, pelajaran, classroom
        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);
        
        $classroom = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title' => 'PPKN Kelas VII-A',
            'status' => 'published',
            'class_code' => 'TESTCODE',
            'created_by' => $user->uuid,
        ]);

        // 4. Assign Eka to teach the class
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);

        // 5. Request the classroom show page
        $response = $this->actingAs($user)->get(route('classroom.show', $classroom));

        // 6. Assert access is granted
        $response->assertStatus(200);
        $response->assertSee('PPKN Kelas VII-A');
    }

    public function test_kesiswaan_user_cannot_access_unassigned_classroom_details()
    {
        // 1. Create a user with 'kesiswaan' access
        $user = User::create([
            'username' => 'eka_kesiswaan',
            'password' => Hash::make('password'),
            'access' => 'kesiswaan',
        ]);

        // 2. Create Guru record with face descriptor
        $guru = Guru::create([
            'id_login' => $user->uuid,
            'nama' => 'Eka Kurniati',
            'nik' => '1234567890',
            'jk' => 'P',
            'face_descriptor' => [0.1, 0.2],
        ]);

        // 3. Create semester, kelas, pelajaran, classroom
        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);
        
        // Create another user to be the classroom creator
        $creator = User::create([
            'username' => 'creator_user',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $classroom = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title' => 'PPKN Kelas VII-A',
            'status' => 'published',
            'class_code' => 'TESTCODE',
            'created_by' => $creator->uuid,
        ]);

        // 4. The teacher is NOT assigned to teach this class.
        // 5. Request the classroom show page
        $response = $this->actingAs($user)->get(route('classroom.show', $classroom));

        // 6. Assert access is forbidden (403)
        $response->assertStatus(403);
    }

    public function test_shared_material_resolves_classroom_dynamically_based_on_class_parameter()
    {
        // 1. Create a user (student in Kelas 7D)
        $user = User::create([
            'username' => 'student_7d',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
        
        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas7B = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $kelas7D = Kelas::create(['tingkat' => 7, 'kelas' => 'D']);
        
        $siswa = \App\Models\Siswa::create([
            'id_login' => $user->uuid,
            'id_kelas' => $kelas7D->uuid,
            'nama' => 'Student 7D',
            'nis' => '12345',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $pelajaran = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);

        // Create Classroom 7B and Classroom 7D
        $classroom7B = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas7B->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title' => 'PPKN Kelas VII-B',
            'status' => 'published',
            'class_code' => 'CODE7B',
            'created_by' => $user->uuid,
        ]);

        $classroom7D = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas7D->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title' => 'PPKN Kelas VII-D',
            'status' => 'published',
            'class_code' => 'CODE7D',
            'created_by' => $user->uuid,
        ]);

        // Auto-enroll the student to Classroom 7D
        \App\Models\ClassroomMember::create([
            'classroom_id' => $classroom7D->uuid,
            'user_id' => $user->uuid,
            'role_in_class' => 'siswa',
            'joined_at' => now(),
        ]);

        // Create a material originally created in 7B
        $material = \App\Models\ClassroomMaterial::create([
            'classroom_id' => $classroom7B->uuid,
            'uploaded_by' => $user->uuid,
            'title' => 'Materi Pancasila',
            'description' => 'Description',
            'is_published' => true,
        ]);

        // Link it to both 7B and 7D
        \Illuminate\Support\Facades\DB::table('classroom_material_links')->insert([
            ['material_id' => $material->uuid, 'classroom_id' => $classroom7B->uuid, 'created_at' => now(), 'updated_at' => now()],
            ['material_id' => $material->uuid, 'classroom_id' => $classroom7D->uuid, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Request the material show page with class = 7D's uuid
        $response = $this->actingAs($user)->get(route('classroom.material.show', [$material, 'class' => $classroom7D->uuid]));

        // Assert they see "PPKN Kelas VII-D" (the dynamic classroom title) and not "PPKN Kelas VII-B"
        $response->assertStatus(200);
        $response->assertSee('PPKN Kelas VII-D');
        $response->assertDontSee('PPKN Kelas VII-B');
    }

    /**
     * Regresi: materi/tugas yg ditaut ke BANYAK kelas (classroom_material_links /
     * classroom_assignment_links) punya SATU kelas "asal" (ClassroomMaterial::classroom(),
     * dipakai buat breadcrumb) tapi bisa ditaut ke kelas lain juga. download()/preview() dulu
     * cuma authorize() ke kelas ASAL itu — siswa yg akses materi/tugas ini lewat kelas MEREKA
     * sendiri (bukan kelas asal) dapat 403 walau halaman show()-nya sendiri sukses dimuat
     * (krn show() sudah benar resolve kelas dinamis). Ini bug produksi nyata (siswa gagal buka
     * PDF materi lewat kelas non-asal) — lihat resolveViewableClassroom() di kedua controller.
     */
    public function test_siswa_di_kelas_non_asal_tetap_bisa_unduh_dan_pratinjau_file_materi(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $student = User::create(['username' => 'student_7d_file', 'password' => Hash::make('password'), 'access' => 'siswa']);
        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas7B = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $kelas7D = Kelas::create(['tingkat' => 7, 'kelas' => 'D']);
        \App\Models\Siswa::create(['id_login' => $student->uuid, 'id_kelas' => $kelas7D->uuid, 'nama' => 'Student 7D', 'nis' => '12346', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $pelajaran = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);
        $classroom7B = Classroom::create(['id_semester' => $semester->id, 'id_kelas' => $kelas7B->uuid, 'id_pelajaran' => $pelajaran->uuid, 'title' => 'PPKN VII-B', 'status' => 'published', 'class_code' => 'FILE7B', 'created_by' => $student->uuid]);
        $classroom7D = Classroom::create(['id_semester' => $semester->id, 'id_kelas' => $kelas7D->uuid, 'id_pelajaran' => $pelajaran->uuid, 'title' => 'PPKN VII-D', 'status' => 'published', 'class_code' => 'FILE7D', 'created_by' => $student->uuid]);

        \App\Models\ClassroomMember::create(['classroom_id' => $classroom7D->uuid, 'user_id' => $student->uuid, 'role_in_class' => 'siswa', 'joined_at' => now()]);

        // Materi dibuat di 7B (kelas "asal"), lalu ditaut jg ke 7D.
        $material = \App\Models\ClassroomMaterial::create([
            'classroom_id' => $classroom7B->uuid, 'uploaded_by' => $student->uuid,
            'title' => 'Materi PDF', 'is_published' => true,
        ]);
        \Illuminate\Support\Facades\DB::table('classroom_material_links')->insert([
            ['material_id' => $material->uuid, 'classroom_id' => $classroom7B->uuid, 'created_at' => now(), 'updated_at' => now()],
            ['material_id' => $material->uuid, 'classroom_id' => $classroom7D->uuid, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $path = 'classroom/materials/bab1.pdf';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, 'isi pdf dummy');
        $file = \App\Models\ClassroomMaterialFile::create([
            'material_id' => $material->uuid, 'original_name' => 'BAB1New.pdf', 'stored_name' => 'bab1.pdf',
            'path' => $path, 'mime' => 'application/pdf', 'size_original' => 100, 'size_compressed' => 100, 'sort_order' => 1,
        ]);

        // Siswa 7D (BUKAN anggota kelas asal 7B) tetap harus bisa unduh & pratinjau.
        $this->actingAs($student)->get(route('classroom.material.file', $file))->assertStatus(200);
        $this->actingAs($student)->get(route('classroom.material.file.preview', $file))->assertStatus(200);
    }

    public function test_siswa_di_kelas_non_asal_tetap_bisa_unduh_file_tugas(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $teacher = User::create(['username' => 'teacher_tugas_file', 'password' => Hash::make('password'), 'access' => 'guru']);
        $student = User::create(['username' => 'student_7d_tugas_file', 'password' => Hash::make('password'), 'access' => 'siswa']);
        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas7B = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $kelas7D = Kelas::create(['tingkat' => 7, 'kelas' => 'D']);
        \App\Models\Siswa::create(['id_login' => $student->uuid, 'id_kelas' => $kelas7D->uuid, 'nama' => 'Student 7D Tugas', 'nis' => '12347', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $pelajaran = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);
        $classroom7B = Classroom::create(['id_semester' => $semester->id, 'id_kelas' => $kelas7B->uuid, 'id_pelajaran' => $pelajaran->uuid, 'title' => 'PPKN VII-B Tugas', 'status' => 'published', 'class_code' => 'TUGAS7B', 'created_by' => $teacher->uuid]);
        $classroom7D = Classroom::create(['id_semester' => $semester->id, 'id_kelas' => $kelas7D->uuid, 'id_pelajaran' => $pelajaran->uuid, 'title' => 'PPKN VII-D Tugas', 'status' => 'published', 'class_code' => 'TUGAS7D', 'created_by' => $teacher->uuid]);

        \App\Models\ClassroomMember::create(['classroom_id' => $classroom7D->uuid, 'user_id' => $student->uuid, 'role_in_class' => 'siswa', 'joined_at' => now()]);

        // Tugas dibuat di 7B (kelas "asal"), ditaut jg ke 7D.
        $assignment = \App\Models\ClassroomAssignment::create([
            'classroom_id' => $classroom7B->uuid, 'created_by' => $teacher->uuid,
            'title' => 'Tugas Ada File', 'status' => 'published', 'max_score' => 100,
        ]);
        \Illuminate\Support\Facades\DB::table('classroom_assignment_links')->insert([
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7B->uuid, 'created_at' => now(), 'updated_at' => now()],
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7D->uuid, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $path = 'classroom/assignments/lembar-kerja.pdf';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, 'isi pdf dummy');
        $file = \App\Models\ClassroomAssignmentFile::create([
            'assignment_id' => $assignment->uuid, 'original_name' => 'LembarKerja.pdf', 'stored_name' => 'lembar-kerja.pdf',
            'path' => $path, 'mime' => 'application/pdf', 'size_original' => 100, 'size_compressed' => 100,
        ]);

        $this->actingAs($student)->get(route('classroom.assignment.file', $file))->assertStatus(200);
    }

    public function test_guru_pengampu_kelas_non_asal_tetap_bisa_unduh_file_submission(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $teacher = User::create(['username' => 'teacher_submission_file', 'password' => Hash::make('password'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $teacher->uuid, 'nama' => 'Guru Submission', 'nik' => '3333333333', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $student = User::create(['username' => 'student_7d_submission_file', 'password' => Hash::make('password'), 'access' => 'siswa']);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas7B = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $kelas7D = Kelas::create(['tingkat' => 7, 'kelas' => 'D']);
        \App\Models\Siswa::create(['id_login' => $student->uuid, 'id_kelas' => $kelas7D->uuid, 'nama' => 'Student 7D Sub', 'nis' => '12348', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $pelajaran = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);
        $classroom7B = Classroom::create(['id_semester' => $semester->id, 'id_kelas' => $kelas7B->uuid, 'id_pelajaran' => $pelajaran->uuid, 'title' => 'PPKN VII-B Sub', 'status' => 'published', 'class_code' => 'SUB7B', 'created_by' => $teacher->uuid]);
        $classroom7D = Classroom::create(['id_semester' => $semester->id, 'id_kelas' => $kelas7D->uuid, 'id_pelajaran' => $pelajaran->uuid, 'title' => 'PPKN VII-D Sub', 'status' => 'published', 'class_code' => 'SUB7D', 'created_by' => $teacher->uuid]);

        // Guru mengajar 7D (bukan 7B — kelas asal tugas) via Ngajar.
        Ngajar::create(['id_guru' => $guru->uuid, 'id_kelas' => $kelas7D->uuid, 'id_pelajaran' => $pelajaran->uuid]);

        $assignment = \App\Models\ClassroomAssignment::create([
            'classroom_id' => $classroom7B->uuid, 'created_by' => $teacher->uuid,
            'title' => 'Tugas Submission', 'status' => 'published', 'max_score' => 100,
        ]);
        \Illuminate\Support\Facades\DB::table('classroom_assignment_links')->insert([
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7B->uuid, 'created_at' => now(), 'updated_at' => now()],
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7D->uuid, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $submission = \App\Models\ClassroomSubmission::create([
            'assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7D->uuid,
            'student_id' => $student->uuid, 'status' => 'submitted', 'submitted_at' => now(),
        ]);

        $path = 'classroom/submissions/jawaban.pdf';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, 'isi pdf dummy');
        $file = \App\Models\ClassroomSubmissionFile::create([
            'submission_id' => $submission->uuid, 'original_name' => 'Jawaban.pdf', 'stored_name' => 'jawaban.pdf',
            'path' => $path, 'mime' => 'application/pdf', 'size_original' => 100, 'size_compressed' => 100,
        ]);

        $this->actingAs($teacher)->get(route('classroom.submission.file', $file))->assertStatus(200);
    }

    /**
     * Regresi bug produksi nyata: siswa di kelas NON-asal (tugas ditaut ke banyak kelas via
     * classroom_assignment_links) dapat 403 "This action is unauthorized" saat kumpul tugas,
     * walau keanggotaannya di kelasnya SENDIRI valid — ClassroomSubmissionController::store()
     * dulu authorize() ke $assignment->classroom (kelas asal) mentah2, bukan resolveClassroom()
     * (yg dipakai show()/download()/lock, sudah benar). classroom:repair-membership TIDAK
     * memperbaiki ini krn keanggotaan siswa memang sudah benar — yg salah adalah controller
     * mengecek ke classroom yg SALAH.
     */
    public function test_siswa_di_kelas_non_asal_tetap_bisa_mengumpulkan_tugas(): void
    {
        $teacher = User::create(['username' => 'teacher_submit_regresi', 'password' => Hash::make('password'), 'access' => 'guru']);
        $student = User::create(['username' => 'student_7d_submit_regresi', 'password' => Hash::make('password'), 'access' => 'siswa']);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas7B = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $kelas7D = Kelas::create(['tingkat' => 7, 'kelas' => 'D']);
        \App\Models\Siswa::create(['id_login' => $student->uuid, 'id_kelas' => $kelas7D->uuid, 'nama' => 'Student 7D Submit', 'nis' => '99901', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $pelajaran = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);
        $classroom7B = Classroom::create(['id_semester' => $semester->id, 'id_kelas' => $kelas7B->uuid, 'id_pelajaran' => $pelajaran->uuid, 'title' => 'PPKN VII-B Submit', 'status' => 'published', 'class_code' => 'SUBMITB', 'created_by' => $teacher->uuid]);
        $classroom7D = Classroom::create(['id_semester' => $semester->id, 'id_kelas' => $kelas7D->uuid, 'id_pelajaran' => $pelajaran->uuid, 'title' => 'PPKN VII-D Submit', 'status' => 'published', 'class_code' => 'SUBMITD', 'created_by' => $teacher->uuid]);

        // Siswa HANYA anggota 7D — bukan kelas asal (7B) tempat tugas dibuat.
        \App\Models\ClassroomMember::create(['classroom_id' => $classroom7D->uuid, 'user_id' => $student->uuid, 'role_in_class' => 'siswa', 'joined_at' => now()]);

        // Tugas dibuat di 7B (kelas asal), ditaut jg ke 7D.
        $assignment = \App\Models\ClassroomAssignment::create([
            'classroom_id' => $classroom7B->uuid, 'created_by' => $teacher->uuid,
            'title' => 'Latihan Regresi', 'status' => 'published', 'max_score' => 100,
        ]);
        \Illuminate\Support\Facades\DB::table('classroom_assignment_links')->insert([
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7B->uuid, 'created_at' => now(), 'updated_at' => now()],
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7D->uuid, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($student)->post(route('classroom.submission.store', $assignment), [
            'body' => 'Jawaban siswa 7D',
            'submit_action' => 'submit',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Tugas berhasil dikumpulkan.');

        // classroom_id yg tersimpan harus classroom7D (kelas siswa SENDIRI), bukan 7B (kelas asal).
        $this->assertDatabaseHas('classroom_submissions', [
            'assignment_id' => $assignment->uuid,
            'student_id'    => $student->uuid,
            'classroom_id'  => $classroom7D->uuid,
            'status'        => 'submitted',
        ]);
    }

    /** Guru pengampu kelas non-asal tetap bisa menilai submission siswa di kelasnya. */
    public function test_guru_pengampu_kelas_non_asal_tetap_bisa_menilai_submission(): void
    {
        $teacher = User::create(['username' => 'teacher_grade_regresi', 'password' => Hash::make('password'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $teacher->uuid, 'nama' => 'Guru Grade Regresi', 'nik' => '4444444444', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $student = User::create(['username' => 'student_grade_regresi', 'password' => Hash::make('password'), 'access' => 'siswa']);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas7B = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $kelas7D = Kelas::create(['tingkat' => 7, 'kelas' => 'D']);
        \App\Models\Siswa::create(['id_login' => $student->uuid, 'id_kelas' => $kelas7D->uuid, 'nama' => 'Student Grade 7D', 'nis' => '99902', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $pelajaran = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);
        $classroom7B = Classroom::create(['id_semester' => $semester->id, 'id_kelas' => $kelas7B->uuid, 'id_pelajaran' => $pelajaran->uuid, 'title' => 'PPKN VII-B Grade', 'status' => 'published', 'class_code' => 'GRADEB', 'created_by' => $teacher->uuid]);
        $classroom7D = Classroom::create(['id_semester' => $semester->id, 'id_kelas' => $kelas7D->uuid, 'id_pelajaran' => $pelajaran->uuid, 'title' => 'PPKN VII-D Grade', 'status' => 'published', 'class_code' => 'GRADED', 'created_by' => $teacher->uuid]);

        // Guru mengajar 7D (bukan 7B — kelas asal tugas) via Ngajar.
        Ngajar::create(['id_guru' => $guru->uuid, 'id_kelas' => $kelas7D->uuid, 'id_pelajaran' => $pelajaran->uuid]);

        $assignment = \App\Models\ClassroomAssignment::create([
            'classroom_id' => $classroom7B->uuid, 'created_by' => $teacher->uuid,
            'title' => 'Tugas Grade Regresi', 'status' => 'published', 'max_score' => 100,
        ]);
        \Illuminate\Support\Facades\DB::table('classroom_assignment_links')->insert([
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7B->uuid, 'created_at' => now(), 'updated_at' => now()],
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7D->uuid, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $submission = \App\Models\ClassroomSubmission::create([
            'assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7D->uuid,
            'student_id' => $student->uuid, 'status' => 'submitted', 'submitted_at' => now(),
        ]);

        $response = $this->actingAs($teacher)->post(route('classroom.submission.grade', $submission), [
            'score' => 88, 'feedback' => 'Bagus',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Nilai disimpan.');
        $this->assertDatabaseHas('classroom_submissions', ['uuid' => $submission->uuid, 'score' => 88, 'status' => 'graded']);
    }

    public function test_comments_and_submissions_are_segregated_by_classroom()
    {
        $teacher = User::create([
            'username' => 'teacher_user',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $teacher->uuid,
            'nama' => 'Teacher',
            'nik' => '1111111111',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $student7B = User::create([
            'username' => 'student_7b',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
        $student7D = User::create([
            'username' => 'student_7d',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas7B = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $kelas7D = Kelas::create(['tingkat' => 7, 'kelas' => 'D']);

        $siswa7B = \App\Models\Siswa::create([
            'id_login' => $student7B->uuid,
            'id_kelas' => $kelas7B->uuid,
            'nama' => 'Student 7B',
            'nis' => '123',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);
        $siswa7D = \App\Models\Siswa::create([
            'id_login' => $student7D->uuid,
            'id_kelas' => $kelas7D->uuid,
            'nama' => 'Student 7D',
            'nis' => '456',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $pelajaran = Pelajaran::create(['nama' => 'Pendidikan Pancasila', 'ringkasan' => 'PPKN', 'kkm' => 75]);

        $classroom7B = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas7B->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title' => 'PPKN Kelas VII-B',
            'status' => 'published',
            'class_code' => 'CODE7B',
            'created_by' => $teacher->uuid,
        ]);

        $classroom7D = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas7D->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title' => 'PPKN Kelas VII-D',
            'status' => 'published',
            'class_code' => 'CODE7D',
            'created_by' => $teacher->uuid,
        ]);

        // Auto-enroll students
        \App\Models\ClassroomMember::create([
            'classroom_id' => $classroom7B->uuid,
            'user_id' => $student7B->uuid,
            'role_in_class' => 'siswa',
            'joined_at' => now(),
        ]);
        \App\Models\ClassroomMember::create([
            'classroom_id' => $classroom7D->uuid,
            'user_id' => $student7D->uuid,
            'role_in_class' => 'siswa',
            'joined_at' => now(),
        ]);

        // Link Eka/Teacher to teach both classes
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas7B->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas7D->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);

        // Create assignment originally in 7B
        $assignment = \App\Models\ClassroomAssignment::create([
            'classroom_id' => $classroom7B->uuid,
            'created_by' => $teacher->uuid,
            'title' => 'Contoh Latihan',
            'status' => 'published',
            'max_score' => 100,
        ]);

        // Link assignment to both classrooms
        \Illuminate\Support\Facades\DB::table('classroom_assignment_links')->insert([
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7B->uuid, 'created_at' => now(), 'updated_at' => now()],
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom7D->uuid, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Post a comment in 7B
        $comment7B = \App\Models\ClassroomComment::create([
            'commentable_type' => \App\Models\ClassroomAssignment::class,
            'commentable_id' => $assignment->uuid,
            'classroom_id' => $classroom7B->uuid,
            'user_id' => $student7B->uuid,
            'body' => 'Komentar di kelas 7B',
        ]);

        // Fetch comments for 7B
        $response7B = $this->actingAs($teacher)->get("/ruang-kelas/comments-json/assignment/{$assignment->uuid}?class={$classroom7B->uuid}");
        $response7B->assertStatus(200);
        $response7B->assertSee('Komentar di kelas 7B');

        // Fetch comments for 7D
        $response7D = $this->actingAs($teacher)->get("/ruang-kelas/comments-json/assignment/{$assignment->uuid}?class={$classroom7D->uuid}");
        $response7D->assertStatus(200);
        $response7D->assertDontSee('Komentar di kelas 7B');

        // Submit comment to 7D as student7D to trigger notification for the teacher
        $responsePost7D = $this->actingAs($student7D)->post(route('classroom.assignment.comment', $assignment), [
            'body' => 'Komentar di kelas 7D',
            'class' => $classroom7D->uuid,
        ]);
        $responsePost7D->assertStatus(302); // redirects back

        // Assert 7D comment exists and is linked to 7D
        $this->assertDatabaseHas('classroom_comments', [
            'body' => 'Komentar di kelas 7D',
            'classroom_id' => $classroom7D->uuid,
        ]);

        // Assert teacher received database notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $teacher->uuid,
            'type' => 'App\Notifications\ClassroomCommentNotification',
        ]);

        // Create a submission for student7B
        $submission7B = \App\Models\ClassroomSubmission::create([
            'assignment_id' => $assignment->uuid,
            'classroom_id' => $classroom7B->uuid,
            'student_id' => $student7B->uuid,
            'status' => 'graded',
            'score' => 90,
            'submitted_at' => now(),
        ]);

        // Create a submission for student7D
        $submission7D = \App\Models\ClassroomSubmission::create([
            'assignment_id' => $assignment->uuid,
            'classroom_id' => $classroom7D->uuid,
            'student_id' => $student7D->uuid,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Access the grading page for 7D
        $responseGrading7D = $this->actingAs($teacher)->get(route('classroom.assignment.grading', [$assignment, 'class' => $classroom7D->uuid]));
        $responseGrading7D->assertStatus(200);
        $responseGrading7D->assertSee('Student 7D'); // Should list Student 7D submission in this class
        $responseGrading7D->assertDontSee('Student 7B'); // Should not show Student 7B submission (since they are in 7B, not 7D)

        // Lock the assignment to test monitoring (specifically for classroom 7D)
        \Illuminate\Support\Facades\DB::table('classroom_assignment_links')
            ->where('assignment_id', $assignment->uuid)
            ->where('classroom_id', $classroom7D->uuid)
            ->update(['is_locked' => true, 'access_token' => 'ABCD']);

        // Student 7D unlocks the assignment using token and classroom 7D context
        $responseUnlock = $this->actingAs($student7D)->post(route('classroom.assignment.unlock', $assignment), [
            'token' => 'ABCD',
            'class' => $classroom7D->uuid,
        ]);
        $responseUnlock->assertStatus(302);
        $responseUnlock->assertRedirect(route('classroom.assignment.show', [$assignment, 'class' => $classroom7D->uuid]));

        // Access lock events monitoring for Classroom 7D
        $responseMonitoring7D = $this->actingAs($teacher)->get(route('classroom.assignment.lockevents', [$assignment, 'class' => $classroom7D->uuid]));
        $responseMonitoring7D->assertStatus(200);
        
        $json7D = $responseMonitoring7D->json();
        $this->assertEquals(1, $json7D['total']);
        $this->assertEquals('Student 7D', $json7D['peserta'][0]['nama']);
        
        // Access lock events monitoring for Classroom 7B
        $responseMonitoring7B = $this->actingAs($teacher)->get(route('classroom.assignment.lockevents', [$assignment, 'class' => $classroom7B->uuid]));
        $responseMonitoring7B->assertStatus(200);
        
        $json7B = $responseMonitoring7B->json();
        $this->assertEquals(1, $json7B['total']);
        $this->assertEquals('Student 7B', $json7B['peserta'][0]['nama']);

        // Assert notification contains classroom_id in its JSON data
        $notification = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_id', $teacher->uuid)
            ->where('type', 'App\Notifications\ClassroomCommentNotification')
            ->first();
        
        $this->assertNotNull($notification);
        $data = json_decode($notification->data, true);
        $this->assertEquals($classroom7D->uuid, $data['classroom_id']);
    }

    public function test_classroom_creator_receives_comment_notifications_even_if_not_in_ngajar()
    {
        $creator = User::create([
            'username' => 'creator_user',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $guruCreator = Guru::create([
            'id_login' => $creator->uuid,
            'nama' => 'Creator Teacher',
            'nik' => '2222222222',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $student = User::create([
            'username' => 'student_user',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);

        $semester = Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'E']);

        $siswa = \App\Models\Siswa::create([
            'id_login' => $student->uuid,
            'id_kelas' => $kelas->uuid,
            'nama' => 'Student E',
            'nis' => '789',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $pelajaran = Pelajaran::create(['nama' => 'Bahasa Sunda', 'ringkasan' => 'SUNDA', 'kkm' => 75]);

        // Creator creates the classroom
        $classroom = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'title' => 'Sunda Kelas VII-E',
            'status' => 'published',
            'class_code' => 'CODE7E',
            'created_by' => $creator->uuid,
        ]);

        // Auto-enroll student
        \App\Models\ClassroomMember::create([
            'classroom_id' => $classroom->uuid,
            'user_id' => $student->uuid,
            'role_in_class' => 'siswa',
            'joined_at' => now(),
        ]);

        // Create assignment (no Ngajar entry exists for $creator)
        $assignment = \App\Models\ClassroomAssignment::create([
            'classroom_id' => $classroom->uuid,
            'created_by' => $creator->uuid,
            'title' => 'Tugas Sunda',
            'status' => 'published',
            'max_score' => 100,
        ]);

        \Illuminate\Support\Facades\DB::table('classroom_assignment_links')->insert([
            ['assignment_id' => $assignment->uuid, 'classroom_id' => $classroom->uuid, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Submit comment to Sunda assignment as student
        $response = $this->actingAs($student)->post(route('classroom.assignment.comment', $assignment), [
            'body' => 'Komen baru sunda',
            'class' => $classroom->uuid,
        ]);
        $response->assertStatus(302);

        // Assert classroom creator received database notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $creator->uuid,
            'type' => 'App\Notifications\ClassroomCommentNotification',
        ]);

        // Assert notification contains classroom_id in its JSON data
        $notification = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_id', $creator->uuid)
            ->first();
        
        $this->assertNotNull($notification);
        $data = json_decode($notification->data, true);
        $this->assertEquals($classroom->uuid, $data['classroom_id']);
    }
}
