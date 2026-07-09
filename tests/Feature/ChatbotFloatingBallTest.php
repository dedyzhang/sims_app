<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verifikasi floating ball chat tampil utk non-admin & tersembunyi utk admin. */
class ChatbotFloatingBallTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access, string $username): User
    {
        return User::create([
            'username' => $username,
            'password' => bcrypt('rahasia123'),
            'access' => $access,
        ]);
    }

    public function test_floating_ball_muncul_untuk_siswa_dan_orangtua(): void
    {
        // Aturan "satu bola per pengguna": floating ball handoff ke admin hanya
        // untuk siswa & orang tua. Staf/admin memakai widget AsistenAI.
        foreach (['siswa', 'orangtua'] as $i => $access) {
            $user = $this->makeUser($access, "fab_{$access}_{$i}");

            $this->actingAs($user)->get('/dashboard')
                ->assertOk()
                ->assertSee('chatFab()', false)
                ->assertSee('Asisten Sekolah', false);
        }
    }

    public function test_floating_ball_tersembunyi_untuk_staf_dan_admin(): void
    {
        // Guru, wali kelas, kurikulum, kepala, dan admin TIDAK dapat floating ball
        // handoff — mereka memakai widget AsistenAI (generatif), bukan chatFab.
        foreach (['guru', 'walikelas', 'kurikulum', 'kepala', 'admin'] as $i => $access) {
            $user = $this->makeUser($access, "nofab_{$access}_{$i}");

            $this->actingAs($user)->get('/dashboard')
                ->assertOk()
                ->assertDontSee('chatFab()', false);
        }
    }
}
