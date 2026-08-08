<?php

namespace Tests\Feature;

use App\Models\JadwalPiket;
use App\Models\User;
use App\Notifications\PiketH1Notification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

class PiketH1ReminderTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    public function test_h1_reminder_mengirim_notifikasi_untuk_guru_piket_besok(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-09 15:00:00')); // Minggu → besok Senin (hari=1)

        $jadwal = JadwalPiket::create([
            'id_guru' => $this->wali7a->guru->uuid,
            'hari' => 1,
            'is_ketua' => false,
        ]);

        $this->artisan('piket:h1-reminder')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->wali7a->uuid,
            'type' => PiketH1Notification::class,
        ]);

        $this->wali7a->refresh();
        $data = $this->wali7a->notifications()->first()->data;
        $this->assertSame($jadwal->uuid, $data['jadwal_piket_id']);
        $this->assertSame('2026-08-10', $data['tanggal_piket']);
    }

    public function test_h1_reminder_idempoten_tidak_mengirim_duplikat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-09 15:00:00'));

        JadwalPiket::create([
            'id_guru' => $this->wali7a->guru->uuid,
            'hari' => 1,
            'is_ketua' => false,
        ]);

        $this->artisan('piket:h1-reminder')->assertSuccessful();
        $this->artisan('piket:h1-reminder')->assertSuccessful();

        $this->assertSame(1, $this->wali7a->notifications()->count());
    }

    public function test_h1_reminder_lewati_akhir_pekan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 15:00:00')); // Jumat → besok Sabtu

        JadwalPiket::create([
            'id_guru' => $this->wali7a->guru->uuid,
            'hari' => 6,
            'is_ketua' => false,
        ]);

        $this->artisan('piket:h1-reminder')
            ->expectsOutput('Besok akhir pekan — tidak ada jadwal piket sekolah.')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }
}
