<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Endpoint upload gambar utk TinyMCE (teks_soal & opsi jawaban) — dipakai lewat
 * images_upload_handler, format respons {location: url} sesuai kontrak TinyMCE.
 */
class UjianSoalUploadGambarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function buatGuru(): User
    {
        $user = User::create(['username' => 'guru_upload_gambar', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $user->uuid, 'nama' => 'Guru Upload', 'nik' => '3030303030', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return $user;
    }

    public function test_guru_bisa_upload_gambar_dan_dapat_url(): void
    {
        $guru = $this->buatGuru();
        $file = UploadedFile::fake()->image('soal.png', 400, 300);

        $res = $this->actingAs($guru)->postJson(route('ujian.soal.unggah-gambar'), ['file' => $file]);

        $res->assertOk();
        $res->assertJsonStructure(['location']);
        $this->assertStringContainsString('/storage/ujian-soal/', $res->json('location'));

        $files = Storage::disk('public')->allFiles();
        $this->assertNotEmpty(array_filter($files, fn ($f) => str_starts_with($f, 'ujian-soal/')));
    }

    public function test_file_bukan_gambar_ditolak(): void
    {
        $guru = $this->buatGuru();
        $file = UploadedFile::fake()->create('soal.pdf', 100, 'application/pdf');

        $this->actingAs($guru)->postJson(route('ujian.soal.unggah-gambar'), ['file' => $file])
            ->assertStatus(422);
    }

    public function test_siswa_tidak_bisa_upload_gambar(): void
    {
        $user = User::create(['username' => 'siswa_upload_gambar', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'nama' => 'Siswa Upload', 'nis' => '9101', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $file = UploadedFile::fake()->image('soal.png');

        $this->actingAs($user)->postJson(route('ujian.soal.unggah-gambar'), ['file' => $file])
            ->assertForbidden();
    }

    public function test_tamu_tak_login_ditolak(): void
    {
        $file = UploadedFile::fake()->image('soal.png');
        $this->postJson(route('ujian.soal.unggah-gambar'), ['file' => $file])->assertUnauthorized();
    }
}
