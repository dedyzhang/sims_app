<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ModeUjian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TickerStatsGracefulTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_graceful_stop_signal_without_401(): void
    {
        // Simulasi tab yang terbuka setelah sesi login kadaluarsa di background.
        // Harus mengembalikan HTTP 200 dengan payload stopped agar tidak mengotori log server dengan 401.
        $response = $this->getJson(route('dashboard.ticker-stats'));

        $response->assertOk()
            ->assertExactJson([
                'auth' => false,
                'stopped' => true,
            ]);
    }

    public function test_authenticated_user_gets_ticker_stats(): void
    {
        $admin = User::create([
            'username' => 'ticker_tester',
            'password' => Hash::make('secret123'),
            'access'   => 'admin',
        ]);

        $response = $this->actingAs($admin)->getJson(route('dashboard.ticker-stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'semester',
                'siswa',
                'guru',
                'kelas',
                'mapel',
                'aset',
                'kerusakan',
                'peminjaman',
                'online',
            ]);
    }

    public function test_authenticated_user_during_mode_ujian_gets_mode_ujian_stop_signal(): void
    {
        ModeUjian::aktifkan();

        $admin = User::create([
            'username' => 'ticker_tester_ujian',
            'password' => Hash::make('secret123'),
            'access'   => 'admin',
        ]);

        $response = $this->actingAs($admin)->getJson(route('dashboard.ticker-stats'));

        $response->assertOk()
            ->assertExactJson([
                'mode_ujian' => true,
            ]);
    }
}
