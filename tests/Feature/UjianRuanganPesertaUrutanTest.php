<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\UjianPaket;
use App\Models\UjianRuangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UjianRuanganPesertaUrutanTest extends TestCase
{
    use RefreshDatabase;

    public function test_peserta_ruangan_diurutkan_per_kelas_dan_abjad_nama(): void
    {
        $guru = User::create(['username' => 'guru_paket', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $paket = UjianPaket::create(['created_by' => $guru->uuid, 'nama' => 'PAS Gasal']);
        $ruangan = UjianRuangan::create(['id_ujian_paket' => $paket->uuid, 'nama' => 'Ruang 1']);

        $kelas8A = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        $kelas7A = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $kelas7B = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);

        // Buat siswa sengaja tidak urut kelas dan nama
        $s1 = Siswa::create(['id_kelas' => $kelas8A->uuid, 'nama' => 'Sylvia Fransisca', 'nis' => '1001', 'jk' => 'P']);
        $s2 = Siswa::create(['id_kelas' => $kelas7A->uuid, 'nama' => 'Vivian', 'nis' => '1002', 'jk' => 'P']);
        $s3 = Siswa::create(['id_kelas' => $kelas7A->uuid, 'nama' => 'Resky Jayland Tan', 'nis' => '1003', 'jk' => 'L']);
        $s4 = Siswa::create(['id_kelas' => $kelas7B->uuid, 'nama' => 'Vivian Zolanda', 'nis' => '1004', 'jk' => 'P']);

        $ruangan->peserta()->create(['id_siswa' => $s1->uuid]);
        $ruangan->peserta()->create(['id_siswa' => $s2->uuid]);
        $ruangan->peserta()->create(['id_siswa' => $s3->uuid]);
        $ruangan->peserta()->create(['id_siswa' => $s4->uuid]);

        $res = $this->actingAs($guru)->get(route('ujian.paket.ruangan.show', [$paket, $ruangan]));
        $res->assertOk();

        // Urutan yang diharapkan:
        // 1. 7A - Resky Jayland Tan
        // 2. 7A - Vivian
        // 3. 7B - Vivian Zolanda
        // 4. 8A - Sylvia Fransisca
        $res->assertSeeInOrder([
            'Resky Jayland Tan',
            'Vivian',
            'Vivian Zolanda',
            'Sylvia Fransisca',
        ]);
    }
}
