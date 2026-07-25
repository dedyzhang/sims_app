<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Support\FaceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Setting mesin pengenalan wajah (Human.js vs InsightFace) — HARUS bisa dibalik kapan saja
 * tanpa kehilangan data, krn descriptor kedua mesin disimpan di kolom terpisah
 * (face_descriptor vs face_descriptor_if).
 */
class FaceEngineTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'admin_face_engine', 'password' => Hash::make('x'), 'access' => 'superadmin']);
    }

    public function test_default_mesin_adalah_human(): void
    {
        $this->assertSame('human', FaceEngine::aktif());
        $this->assertFalse(FaceEngine::isInsightFace());
        $this->assertSame('face_descriptor', FaceEngine::kolomDescriptor());
    }

    public function test_setting_menerima_insightface(): void
    {
        $this->actingAs($this->admin())
            ->post(route('setting.faceEngine'), ['face_engine' => 'insightface'])
            ->assertRedirect();

        $this->assertSame('insightface', Setting::get('face_engine'));
        $this->assertTrue(FaceEngine::isInsightFace());
        $this->assertSame('face_descriptor_if', FaceEngine::kolomDescriptor());
    }

    public function test_setting_menolak_nilai_asing(): void
    {
        $this->actingAs($this->admin())
            ->post(route('setting.faceEngine'), ['face_engine' => 'ngawur'])
            ->assertSessionHasErrors('face_engine');
    }

    public function test_halaman_setting_menampilkan_pilihan_mesin_tanpa_label_percobaan(): void
    {
        $this->actingAs($this->admin())
            ->get(route('setting.index'))
            ->assertOk()
            ->assertSee('Mesin Pengenalan Wajah')
            ->assertSee('InsightFace / ArcFace')
            ->assertDontSee('(percobaan)', false);
    }

    public function test_pindah_mesin_tidak_menghapus_data_mesin_lain(): void
    {
        // Regresi paling penting: kolom terpisah, jadi ganti setting bolak-balik tidak boleh
        // pernah menyentuh data descriptor mesin yg TIDAK aktif saat itu.
        $kelas = \App\Models\Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $siswa = Siswa::create([
            'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Dua Mesin', 'nis' => '999001', 'jk' => 'L',
            'face_descriptor' => [[0.1, 0.2, 0.3]],
            'face_descriptor_if' => [[0.9, 0.8, 0.7, 0.6]],
        ]);

        Setting::set('face_engine', 'human');
        $siswa->refresh();
        $this->assertSame([[0.1, 0.2, 0.3]], $siswa->face_descriptor);
        $this->assertSame([[0.9, 0.8, 0.7, 0.6]], $siswa->face_descriptor_if);

        Setting::set('face_engine', 'insightface');
        $siswa->refresh();
        // Masih utuh dua-duanya setelah pindah setting bolak-balik.
        $this->assertSame([[0.1, 0.2, 0.3]], $siswa->face_descriptor);
        $this->assertSame([[0.9, 0.8, 0.7, 0.6]], $siswa->face_descriptor_if);
    }
}
