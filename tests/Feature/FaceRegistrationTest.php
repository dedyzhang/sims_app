<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FaceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'face_admin'],
            [
                'password' => Hash::make('password'),
                'access'   => 'superadmin',
            ]
        );
    }

    private function studentUser(): array
    {
        $user = User::create([
            'username' => 'face_student',
            'password' => Hash::make('password'),
            'access'   => 'siswa',
        ]);

        $siswa = Siswa::create([
            'id_login' => $user->getKey(),
            'nama'     => 'Siswa Face',
            'nis'      => 'FACE001',
            'jk'       => 'L',
        ]);

        return [$user, $siswa];
    }

    private function descriptors(int $count): array
    {
        $descriptors = [];
        for ($sample = 1; $sample <= $count; $sample++) {
            $descriptors[] = array_fill(0, 64, $sample / 100);
        }

        return $descriptors;
    }

    public function test_wajah_saya_menolak_kurang_dari_tiga_sampel(): void
    {
        [$user, $siswa] = $this->studentUser();

        $this->actingAs($user)
            ->postJson('/wajah-saya', [
                'descriptors' => $this->descriptors(2),
                'photo'       => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('descriptors');

        $this->assertNull($siswa->fresh()->face_descriptor);
    }

    public function test_wajah_saya_menyimpan_tiga_sampel(): void
    {
        [$user, $siswa] = $this->studentUser();

        $this->actingAs($user)
            ->postJson('/wajah-saya', [
                'descriptors' => $this->descriptors(3),
                'photo'       => null,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(3, $siswa->fresh()->face_descriptor);
    }

    public function test_admin_registrasi_wajah_siswa_wajib_tiga_sampel(): void
    {
        $siswa = Siswa::create([
            'nama' => 'Siswa Admin Face',
            'nis'  => 'FACE002',
            'jk'   => 'P',
        ]);

        $this->actingAs($this->admin())
            ->postJson("/siswa/{$siswa->uuid}/wajah", [
                'descriptors' => $this->descriptors(2),
                'photo'       => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('descriptors');

        $this->actingAs($this->admin())
            ->postJson("/siswa/{$siswa->uuid}/wajah", [
                'descriptors' => $this->descriptors(3),
                'photo'       => null,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(3, $siswa->fresh()->face_descriptor);
    }

    public function test_admin_registrasi_wajah_guru_wajib_tiga_sampel(): void
    {
        $guru = Guru::create([
            'nama' => 'Guru Face',
            'nik'  => 'GFACE001',
            'jk'   => 'L',
        ]);

        $this->actingAs($this->admin())
            ->postJson("/guru/{$guru->uuid}/wajah", [
                'descriptors' => $this->descriptors(2),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('descriptors');

        $this->actingAs($this->admin())
            ->postJson("/guru/{$guru->uuid}/wajah", [
                'descriptors' => $this->descriptors(3),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(3, $guru->fresh()->face_descriptor);
    }
}