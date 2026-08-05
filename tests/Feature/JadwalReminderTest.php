<?php

namespace Tests\Feature;

use App\Models\Agenda;
use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\User;
use App\Notifications\JadwalAgendaReminderNotification;
use App\Notifications\JadwalDigestHarianNotification;
use App\Notifications\JadwalSesiReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengingat jadwal mengajar: digest pagi (jadwal:digest-harian) & pengingat
 * per-sesi 10 menit sebelum jam mulai (jadwal:sesi-reminder). Fokus: notifikasi
 * benar terbentuk, idempoten, dan slot non-mengajar (istirahat / tanpa guru)
 * tidak ikut memicu.
 */
class JadwalReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;
    private Guru $guru;
    private Kelas $kelas;
    private Pelajaran $pelajaran;

    protected function setUp(): void
    {
        parent::setUp();

        // Bersihkan slot jam bawaan seeder migration agar test membangun sendiri.
        JamPelajaran::query()->delete();

        $this->guruUser = User::create([
            'username' => 'guru_reminder',
            'password' => Hash::make('x'),
            'access' => 'guru',
        ]);
        $this->guru = Guru::create([
            'id_login' => $this->guruUser->uuid,
            'nama' => 'Bu Ani',
            'nik' => '111',
            'jk' => 'P',
        ]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1, 'jp' => 4]);
    }

    /** @return Jadwal sesi mengajar milik guru default pada hari & jam tertentu */
    private function buatSesi(int $hari, string $jamMulai, string $jamSelesai): Jadwal
    {
        return Jadwal::create([
            'id_kelas' => $this->kelas->uuid,
            'hari' => $hari,
            'jam_ke' => 1,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_guru' => $this->guru->uuid,
        ]);
    }

    // ===== Digest harian =====

    public function test_digest_harian_mengirim_satu_notifikasi_berisi_semua_sesi(): void
    {
        $hari = Carbon::now()->dayOfWeekIso;
        if ($hari === 7) { // Minggu tidak ada di jadwals — geser waktu uji ke Senin.
            Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY));
            $hari = 1;
        }
        $this->buatSesi($hari, '07:00:00', '07:40:00');
        $this->buatSesi($hari, '08:00:00', '08:40:00');

        $this->artisan('jadwal:digest-harian')->assertSuccessful();

        $this->assertSame(1, $this->guruUser->notifications()->count());
        $notif = $this->guruUser->notifications()->first();
        $this->assertSame(JadwalDigestHarianNotification::class, $notif->type);
        $this->assertSame('jadwal_harian', $notif->data['type']);
        $this->assertStringContainsString('2 sesi mengajar', $notif->data['message']);

        Carbon::setTestNow();
    }

    public function test_digest_harian_idempoten(): void
    {
        $hari = Carbon::now()->dayOfWeekIso === 7 ? 1 : Carbon::now()->dayOfWeekIso;
        if (Carbon::now()->dayOfWeekIso === 7) {
            Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY));
        }
        $this->buatSesi($hari, '07:00:00', '07:40:00');

        $this->artisan('jadwal:digest-harian')->assertSuccessful();
        $this->artisan('jadwal:digest-harian')->assertSuccessful();

        $this->assertSame(1, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    // ===== Pengingat per-sesi =====

    public function test_sesi_reminder_mengirim_saat_dalam_window_10_menit(): void
    {
        // Bekukan waktu di Senin 06:50 → band (07:00, 07:05]; sesi 07:02 harus kena.
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(6, 50));
        $this->buatSesi(1, '07:02:00', '07:42:00');

        $this->artisan('jadwal:sesi-reminder')->assertSuccessful();

        $this->assertSame(1, $this->guruUser->notifications()->count());
        $notif = $this->guruUser->notifications()->first();
        $this->assertSame(JadwalSesiReminderNotification::class, $notif->type);
        $this->assertSame('jadwal_sesi', $notif->data['type']);
        $this->assertStringContainsString('Matematika', $notif->data['message']);
        $this->assertStringContainsString('Kelas 7 A', $notif->data['message']);

        Carbon::setTestNow();
    }

    public function test_sesi_reminder_tidak_mengirim_di_luar_window(): void
    {
        // 06:50 → band (07:00, 07:05]; sesi 08:00 masih jauh, tidak kena.
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(6, 50));
        $this->buatSesi(1, '08:00:00', '08:40:00');

        $this->artisan('jadwal:sesi-reminder')->assertSuccessful();

        $this->assertSame(0, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    public function test_sesi_reminder_idempoten_dalam_window(): void
    {
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(6, 50));
        $this->buatSesi(1, '07:02:00', '07:42:00');

        $this->artisan('jadwal:sesi-reminder')->assertSuccessful();
        $this->artisan('jadwal:sesi-reminder')->assertSuccessful();

        $this->assertSame(1, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    public function test_sesi_reminder_blok_berurutan_hanya_diingatkan_sekali(): void
    {
        // Blok double-period berurutan (07:00–07:40 lalu 07:40–08:20). Pada 06:50,
        // hanya jam pertama (07:00) yang jatuh di window [07:00,07:05) → satu pengingat.
        // Jam kedua adalah lanjutan (mulai 07:40 = selesai jam pertama) → tak diingatkan.
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(6, 50));
        $this->buatSesi(1, '07:00:00', '07:40:00');
        $this->buatSesi(1, '07:40:00', '08:20:00');

        $this->artisan('jadwal:sesi-reminder')->assertSuccessful();

        $this->assertSame(1, $this->guruUser->notifications()->count());

        // Saat jam kedua akan dimulai (07:30 → window [07:40,07:45)), lanjutan blok
        // tetap TIDAK memicu pengingat kedua.
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(7, 30));
        $this->artisan('jadwal:sesi-reminder')->assertSuccessful();
        $this->assertSame(1, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    public function test_sesi_reminder_mapel_sama_tak_berurutan_tetap_diingatkan_terpisah(): void
    {
        // Mapel sama tapi ADA JEDA (07:00–07:40 lalu 09:00–09:40) bukan lanjutan blok
        // → masing-masing dapat pengingat sendiri. Uji saat jam kedua (08:50).
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(8, 50));
        $this->buatSesi(1, '07:00:00', '07:40:00');
        $this->buatSesi(1, '09:00:00', '09:40:00');

        $this->artisan('jadwal:sesi-reminder')->assertSuccessful();

        $this->assertSame(1, $this->guruUser->notifications()->count());
        $this->assertStringContainsString('09:00', $this->guruUser->notifications()->first()->data['message']);

        Carbon::setTestNow();
    }

    // ===== Pengecualian =====

    public function test_slot_tanpa_pelajaran_tidak_memicu_notifikasi(): void
    {
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(6, 50));
        // Slot istirahat: tanpa id_pelajaran & tanpa id_guru, mulai 07:02.
        Jadwal::create([
            'id_kelas' => $this->kelas->uuid,
            'hari' => 1,
            'jam_mulai' => '07:02:00',
            'jam_selesai' => '07:20:00',
            'keterangan' => 'Istirahat',
        ]);

        $this->artisan('jadwal:sesi-reminder')->assertSuccessful();
        $this->artisan('jadwal:digest-harian')->assertSuccessful();

        $this->assertSame(0, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    public function test_guru_tanpa_akun_login_tidak_menghentikan_proses(): void
    {
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(6, 50));
        $guruTanpaAkun = Guru::create(['nama' => 'Pak Budi', 'nik' => '222', 'jk' => 'L']);
        Jadwal::create([
            'id_kelas' => $this->kelas->uuid,
            'hari' => 1,
            'jam_ke' => 1,
            'jam_mulai' => '07:02:00',
            'jam_selesai' => '07:42:00',
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_guru' => $guruTanpaAkun->uuid,
        ]);

        $this->artisan('jadwal:sesi-reminder')->assertSuccessful();

        $this->assertSame(0, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    // ===== Pengingat isi agenda (setelah selesai) =====

    public function test_agenda_reminder_mengirim_tepat_setelah_sesi_selesai(): void
    {
        // Jalankan pada 07:42 → band (07:37, 07:42]; sesi selesai 07:40 harus kena.
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(7, 42));
        $this->buatSesi(1, '07:00:00', '07:40:00');

        $this->artisan('jadwal:agenda-reminder')->assertSuccessful();

        $this->assertSame(1, $this->guruUser->notifications()->count());
        $notif = $this->guruUser->notifications()->first();
        $this->assertSame(JadwalAgendaReminderNotification::class, $notif->type);
        $this->assertSame('jadwal_agenda', $notif->data['type']);
        $this->assertStringContainsString('isi agenda', $notif->data['message']);
        $this->assertStringContainsString('07:00–07:40', $notif->data['message']);
        $this->assertStringContainsString('/agenda/buat?tanggal=', $notif->data['url']);

        Carbon::setTestNow();
    }

    public function test_agenda_reminder_belum_dikirim_saat_sesi_belum_selesai(): void
    {
        // 07:20 → sesi baru selesai 07:40, belum lewat → tidak dikirim.
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(7, 20));
        $this->buatSesi(1, '07:00:00', '07:40:00');

        $this->artisan('jadwal:agenda-reminder')->assertSuccessful();

        $this->assertSame(0, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    public function test_agenda_reminder_tidak_dikirim_bila_agenda_sudah_diisi(): void
    {
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(7, 42));
        $sesi = $this->buatSesi(1, '07:00:00', '07:40:00');
        Agenda::create([
            'tanggal' => Carbon::now()->toDateString(),
            'id_jadwal' => $sesi->uuid,
            'id_guru' => $this->guru->uuid,
            'id_kelas' => $this->kelas->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'pembahasan' => 'sudah', 'metode' => 'ceramah', 'proses' => 'selesai',
            'kegiatan' => 'x', 'kendala' => '-', 'validasi' => 'belum', 'semester' => 1,
        ]);

        $this->artisan('jadwal:agenda-reminder')->assertSuccessful();

        $this->assertSame(0, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    public function test_agenda_reminder_idempoten(): void
    {
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(7, 42));
        $this->buatSesi(1, '07:00:00', '07:40:00');

        $this->artisan('jadwal:agenda-reminder')->assertSuccessful();
        $this->artisan('jadwal:agenda-reminder')->assertSuccessful();

        $this->assertSame(1, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    public function test_agenda_reminder_double_period_hanya_satu_pengingat(): void
    {
        // Dua jam berurutan kelas+mapel sama → satu agenda → satu pengingat,
        // dipicu setelah jam TERAKHIR (08:20) selesai, bukan setelah 07:40.
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(8, 22));
        $this->buatSesi(1, '07:00:00', '07:40:00');
        $this->buatSesi(1, '07:40:00', '08:20:00');

        $this->artisan('jadwal:agenda-reminder')->assertSuccessful();

        $this->assertSame(1, $this->guruUser->notifications()->count());
        $notif = $this->guruUser->notifications()->first();
        // Rentang gabungan menutup kedua jam.
        $this->assertStringContainsString('07:00–08:20', $notif->data['message']);

        Carbon::setTestNow();
    }

    public function test_agenda_reminder_menyusul_saat_tick_terlewat(): void
    {
        // Self-healing: sesi selesai 07:40 tapi command baru jalan 08:30 (tick
        // sebelumnya terlewat). Pengingat tetap dikirim, bukan hilang permanen.
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(8, 30));
        $this->buatSesi(1, '07:00:00', '07:40:00');

        $this->artisan('jadwal:agenda-reminder')->assertSuccessful();

        $this->assertSame(1, $this->guruUser->notifications()->count());
        $this->assertSame('jadwal_agenda', $this->guruUser->notifications()->first()->data['type']);

        // Dan tetap idempoten pada run-run berikutnya sepanjang hari.
        $this->artisan('jadwal:agenda-reminder')->assertSuccessful();
        $this->assertSame(1, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    public function test_agenda_reminder_guru_tanpa_akun_login_tidak_menghentikan_proses(): void
    {
        // Sesi selesai milik guru tanpa akun login tidak boleh crash / menghentikan
        // pengiriman untuk guru lain, dan tidak menghasilkan notifikasi.
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(8, 30));
        $guruTanpaAkun = Guru::create(['nama' => 'Pak Budi', 'nik' => '333', 'jk' => 'L']);
        Jadwal::create([
            'id_kelas' => $this->kelas->uuid,
            'hari' => 1,
            'jam_ke' => 1,
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '07:40:00',
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_guru' => $guruTanpaAkun->uuid,
        ]);

        $this->artisan('jadwal:agenda-reminder')->assertSuccessful();

        $this->assertSame(0, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }

    public function test_agenda_reminder_slot_tanpa_jam_diabaikan(): void
    {
        // Baris jadwal ber-guru & ber-mapel tapi tanpa jam (kolom & relasi jam null):
        // tak bisa dijadwalkan → tidak crash & tidak menghasilkan pengingat.
        Carbon::setTestNow(Carbon::now()->next(Carbon::MONDAY)->setTime(8, 30));
        Jadwal::create([
            'id_kelas' => $this->kelas->uuid,
            'hari' => 1,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_guru' => $this->guru->uuid,
            // jam_mulai / jam_selesai / id_jam sengaja tidak diisi
        ]);

        $this->artisan('jadwal:agenda-reminder')->assertSuccessful();

        $this->assertSame(0, $this->guruUser->notifications()->count());

        Carbon::setTestNow();
    }
}
