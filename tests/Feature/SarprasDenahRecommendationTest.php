<?php

namespace Tests\Feature;

use App\Models\User;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarprasDenahRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_denah_sekolah_menampilkan_standar_dan_status_ruangan(): void
    {
        $admin = User::create([
            'username' => 'sap_denah_rekomendasi',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);

        $denah = Denah::create([
            'nama' => 'Gedung A - Lantai 1',
            'gedung' => 'Gedung A',
            'lantai' => '1',
        ]);

        DenahRuangan::create([
            'denah_id' => $denah->id,
            'kode' => 'A-101',
            'nama' => 'Kelas 7A',
            'status' => 'tersedia',
            'kapasitas' => 32,
        ]);

        DenahRuangan::create([
            'denah_id' => $denah->id,
            'kode' => 'LAB-IPA',
            'nama' => 'Laboratorium IPA',
            'status' => 'maintenance',
            'kapasitas' => 28,
        ]);

        $this->actingAs($admin)
            ->get('/sarpras/denah')
            ->assertOk()
            ->assertSee('Denah Sekolah')
            ->assertSee('Standar Denah Sekolah')
            ->assertSee('Rekomendasi Zona Warna')
            ->assertSee('Akademik')
            ->assertSee('Fasilitas Umum')
            ->assertSee('Area Risiko')
            ->assertSee('Gedung A - Lantai 1')
            ->assertSee('2 ruangan')
            ->assertSee('Tersedia')
            ->assertSee('Maintenance');
    }
}
