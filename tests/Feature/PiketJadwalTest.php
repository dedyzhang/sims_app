<?php

namespace Tests\Feature;

use App\Models\JadwalPiket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

class PiketJadwalTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    public function test_admin_dapat_menyimpan_satu_ketua_untuk_setiap_hari_kerja(): void
    {
        $payload = [
            'jadwal' => [
                ['id' => $this->wali7a->guru->uuid, 'hari' => [1, 3, 5]],
                ['id' => $this->wali7b->guru->uuid, 'hari' => [2, 4]],
            ],
            'ketua' => [
                1 => $this->wali7a->guru->uuid,
                2 => $this->wali7b->guru->uuid,
                3 => $this->wali7a->guru->uuid,
                4 => $this->wali7b->guru->uuid,
                5 => $this->wali7a->guru->uuid,
            ],
        ];

        $this->actingAs($this->admin)
            ->postJson(route('piket.jadwal.simpan'), $payload)
            ->assertOk();

        $this->assertDatabaseCount('jadwal_piket', 5);
        $this->assertSame(5, JadwalPiket::where('is_ketua', true)->count());
        $this->assertSame(
            $this->wali7b->guru->uuid,
            JadwalPiket::where('hari', 4)->value('id_guru')
        );
    }

    public function test_ketua_harus_merupakan_guru_piket_di_hari_tersebut(): void
    {
        $payload = [
            'jadwal' => [
                ['id' => $this->wali7a->guru->uuid, 'hari' => [1, 2, 3, 4, 5]],
            ],
            'ketua' => [
                1 => $this->wali7b->guru->uuid,
                2 => $this->wali7a->guru->uuid,
                3 => $this->wali7a->guru->uuid,
                4 => $this->wali7a->guru->uuid,
                5 => $this->wali7a->guru->uuid,
            ],
        ];

        $this->actingAs($this->admin)
            ->postJson(route('piket.jadwal.simpan'), $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ketua piket hari ke-1 harus terdaftar sebagai guru piket pada hari tersebut.');
    }
}
