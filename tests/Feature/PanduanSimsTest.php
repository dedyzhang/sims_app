<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanduanSimsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access = 'siswa'): User
    {
        return User::create([
            'username' => 'panduan_'.$access,
            'password' => Hash::make('password'),
            'access' => $access,
        ]);
    }

    public function test_guest_diarahkan_ke_login(): void
    {
        $this->get('/panduan-sims')->assertRedirect(route('login'));
    }

    public function test_user_tanpa_data_wajah_tetap_bisa_membuka_panduan(): void
    {
        $user = $this->makeUser('siswa');

        $this->actingAs($user)
            ->get('/panduan-sims')
            ->assertOk()
            ->assertSee('Panduan SIMS')
            ->assertSee('Alur Awal Penggunaan')
            ->assertDontSee('docs/PANDUAN_PENGGUNAAN_SIMS_APP.md');
    }

    public function test_panduan_menampilkan_konten_dari_file_markdown(): void
    {
        $user = $this->makeUser('admin');

        $this->actingAs($user)
            ->get('/panduan-sims')
            ->assertOk()
            ->assertSee('Data Master')
            ->assertSee('Fitur Asisten Guru')
            ->assertSee('Generator Soal')
            ->assertSee('RPM Learning')
            ->assertSee('Draft Feedback')
            ->assertSee('Keuangan SPP')
            ->assertSee('Sarana dan Prasarana');
    }
}
