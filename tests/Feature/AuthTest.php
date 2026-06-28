<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature test alur autentikasi + fix keamanan (throttle login, ganti password).
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_berhasil_dengan_username_dan_password_benar(): void
    {
        $user = User::create([
            'username' => 'budi',
            'password' => 'rahasia123',
            'access'   => 'admin',
        ]);

        $response = $this->post('/login', [
            'credential' => 'budi',
            'password'   => 'rahasia123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_login_bisa_pakai_identifier_nik_atau_nis(): void
    {
        $user = User::create([
            'username'   => 'siti',
            'identifier' => '199001012020',
            'password'   => 'rahasia123',
            'access'     => 'admin',
        ]);

        $response = $this->post('/login', [
            'credential' => '199001012020',
            'password'   => 'rahasia123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_login_gagal_dengan_password_salah_tidak_terautentikasi(): void
    {
        User::create([
            'username' => 'budi',
            'password' => 'rahasia123',
            'access'   => 'admin',
        ]);

        $response = $this->post('/login', [
            'credential' => 'budi',
            'password'   => 'password-salah',
        ]);

        $response->assertSessionHasErrors('credential');
        $this->assertGuest();
    }

    public function test_user_dengan_must_change_password_diarahkan_ganti_password(): void
    {
        User::create([
            'username'             => 'barusaja',
            'password'             => 'bawaan123',
            'access'               => 'admin',
            'must_change_password' => true,
        ]);

        $response = $this->post('/login', [
            'credential' => 'barusaja',
            'password'   => 'bawaan123',
        ]);

        $response->assertRedirect(route('ganti.password'));
    }

    public function test_login_dibatasi_rate_limit_setelah_lima_percobaan_gagal(): void
    {
        User::create([
            'username' => 'target',
            'password' => 'rahasia123',
            'access'   => 'admin',
        ]);

        // 5 percobaan pertama hanya gagal biasa (302 back dengan error).
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'credential' => 'target',
                'password'   => 'salah',
            ])->assertStatus(302);
        }

        // Percobaan ke-6 untuk kredensial yang sama harus diblok (429).
        $this->post('/login', [
            'credential' => 'target',
            'password'   => 'salah',
        ])->assertStatus(429);
    }

    public function test_ganti_password_berhasil_dengan_password_lama_benar(): void
    {
        $user = User::create([
            'username' => 'gantipw',
            'password' => 'lama12345',
            'access'   => 'admin',
        ]);

        $response = $this->actingAs($user)->post('/ganti-password', [
            'current_password'          => 'lama12345',
            'new_password'              => 'baru12345',
            'new_password_confirmation' => 'baru12345',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));
    }

    public function test_ganti_password_ditolak_bila_password_lama_salah(): void
    {
        $user = User::create([
            'username' => 'gantipw',
            'password' => 'lama12345',
            'access'   => 'admin',
        ]);

        $response = $this->actingAs($user)->post('/ganti-password', [
            'current_password'          => 'bukan-password-lama',
            'new_password'              => 'baru12345',
            'new_password_confirmation' => 'baru12345',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('lama12345', $user->fresh()->password));
    }

    public function test_request_reset_menyimpan_token_terhash_bukan_plaintext(): void
    {
        $user = User::create([
            'username' => 'lupa',
            'password' => 'rahasia123',
            'access'   => 'admin',
        ]);

        $this->post('/password/request', ['credential' => 'lupa'])
            ->assertSessionHas('success');

        $token = $user->fresh()->reset_token;
        $this->assertNotNull($token);
        // Token bcrypt ter-hash diawali $2y$ — bukan string acak plaintext.
        $this->assertStringStartsWith('$2y$', $token);
    }

    public function test_request_reset_akun_tidak_ditemukan_memberi_error(): void
    {
        $this->post('/password/request', ['credential' => 'tidak-ada'])
            ->assertSessionHasErrors('credential');
    }
}
