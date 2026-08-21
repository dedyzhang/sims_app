<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarprasFotoPickerCameraFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_lapor_kerusakan_menyediakan_fallback_saat_kamera_live_ditolak_browser(): void
    {
        $guru = User::create([
            'username' => 'guru_foto_picker_fallback',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $this->actingAs($guru)
            ->get('/sarpras/kerusakan-lapor')
            ->assertOk()
            ->assertSee('Kamera Langsung')
            ->assertSee('Kamera Perangkat')
            ->assertSee('openKameraPerangkat', false)
            ->assertSee('FileReader', false)
            ->assertSee('x-on:error="it.broken = true"', false)
            ->assertSee('Foto siap dikirim', false)
            ->assertSee('object-cover', false)
            ->assertDontSee('object-contain', false)
            ->assertSee('Izin kamera live ditolak atau diblokir browser', false);
    }
}
