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

    public function test_floating_ball_muncul_untuk_non_admin(): void
    {
        foreach (['siswa', 'guru', 'walikelas', 'kurikulum', 'kepala'] as $i => $access) {
            $user = $this->makeUser($access, "fab_{$access}_{$i}");

            $this->actingAs($user)->get('/dashboard')
                ->assertOk()
                ->assertSee('chatFab()', false)
                ->assertSee('Asisten Sekolah', false);
        }
    }

    public function test_floating_ball_tersembunyi_untuk_admin(): void
    {
        $admin = $this->makeUser('admin', 'fab_admin');

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('chatFab()', false);
    }
}
