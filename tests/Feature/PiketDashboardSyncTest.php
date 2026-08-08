<?php

namespace Tests\Feature;

use App\Models\GuruTidakHadir;
use App\Models\JadwalPiket;
use App\Models\PresensiGuru;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

class PiketDashboardSyncTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    public function test_dashboard_guru_piket_menyinkronkan_presensi_ke_widget(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:00:00')); // Senin
        $hari = 1;

        JadwalPiket::create([
            'id_guru' => $this->wali7a->guru->uuid,
            'hari' => $hari,
            'is_ketua' => true,
        ]);

        PresensiGuru::create([
            'id_guru' => $this->wali7b->guru->uuid,
            'tanggal' => '2026-08-10',
            'status' => 'izin',
            'keterangan' => 'Keperluan keluarga',
        ]);

        $html = $this->actingAs($this->wali7a)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Pak Budi', $html);
        $this->assertDatabaseHas('guru_tidak_hadir', [
            'id_guru' => $this->wali7b->guru->uuid,
            'sumber' => 'otomatis_presensi',
            'alasan' => GuruTidakHadir::ALASAN_DARI_PRESENSI['izin'],
        ]);
    }
}
