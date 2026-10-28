<?php

namespace Tests\Feature;

use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianSoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UjianSimulasiCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulasi_command_runs_and_leaves_zero_database_residue(): void
    {
        $mapel = Pelajaran::create(['nama' => 'Matematika']);
        $guru = User::create([
            'username' => 'guru_simulasi_test',
            'password' => 'secret',
            'access' => 'guru',
        ]);

        $ujian = Ujian::create([
            'judul' => 'Ujian Simulasi Test',
            'id_pelajaran' => $mapel->uuid,
            'created_by' => $guru->uuid,
            'durasi_menit' => 60,
            'status' => 'aktif',
        ]);

        // Buat 2 soal
        $soal = UjianSoal::create([
            'id_ujian' => $ujian->uuid,
            'teks_soal' => 'Berapa 1 + 1?',
            'tipe' => 'mcq',
            'poin' => 10,
        ]);
        $soal->opsi()->create(['teks_opsi' => '2', 'is_benar' => true]);
        $soal->opsi()->create(['teks_opsi' => '3', 'is_benar' => false]);

        $initialUserCount = User::count();
        $initialAttemptCount = UjianAttempt::count();

        // Jalankan perintah simulasi 20 siswa virtual
        $this->artisan('ujian:simulasi', [
            'ujian' => $ujian->uuid,
            '--siswa' => 20,
            '--mode-ujian' => 1,
        ])->assertSuccessful();

        // Pastikan jumlah user dan attempt SAMA PERSIS seperti semula (0 residu akibat rollback)
        $this->assertSame($initialUserCount, User::count());
        $this->assertSame($initialAttemptCount, UjianAttempt::count());
    }
}
