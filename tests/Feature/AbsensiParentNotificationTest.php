<?php

namespace Tests\Feature;

use App\Jobs\SendFcmNotificationJob;
use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AbsensiParentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'absensi_parent_admin'],
            [
                'password' => Hash::make('password'),
                'access' => 'superadmin',
            ]
        );
    }

    private function siswaDenganOrangtua(): array
    {
        $kelas = Kelas::create([
            'tingkat' => 7,
            'kelas' => 'A',
        ]);

        $siswaUser = User::create([
            'username' => 'siswa_absensi_parent',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);

        $parentUser = User::create([
            'username' => 'ortu_absensi_parent',
            'password' => Hash::make('password'),
            'access' => 'orangtua',
        ]);

        $siswa = Siswa::create([
            'id_login' => $siswaUser->uuid,
            'nama' => 'Budi Santoso',
            'nis' => 'ABS-PARENT-001',
            'id_kelas' => $kelas->uuid,
            'jk' => 'L',
        ]);

        Orangtua::create([
            'id_siswa' => $siswa->uuid,
            'id_login' => $parentUser->uuid,
        ]);

        return [$siswa, $kelas, $parentUser, $siswaUser];
    }

    private function aktifkanQrGeolocation(): string
    {
        Setting::set('cara_absensi_guru', 'barcode');
        Setting::set('qr_absensi_aktif', '1');
        Setting::set('sekolah_lat', '-6.200000');
        Setting::set('sekolah_lng', '106.816666');
        Setting::set('absen_radius', '100');

        return substr(hash_hmac('sha256', 'qrabsen|'.now()->toDateString(), (string) config('app.key')), 0, 12);
    }

    public function test_scan_wajah_pertama_mengirim_notifikasi_ke_akun_orang_tua(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-13 07:12:00'));
        [$siswa, $kelas, $parentUser] = $this->siswaDenganOrangtua();

        $this->actingAs($this->admin())->postJson('/absensi/mark', [
            'id_siswa' => $siswa->uuid,
            'id_kelas' => $kelas->uuid,
            'tanggal' => '2026-07-13',
            'status' => 'hadir',
        ])->assertOk()->assertJson([
            'success' => true,
            'jam' => '07:12',
        ]);

        $this->assertDatabaseHas('absensis', [
            'id_siswa' => $siswa->uuid,
            'id_kelas' => $kelas->uuid,
            'tanggal' => '2026-07-13',
            'status' => 'hadir',
            'jam_masuk' => '07:12:00',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $parentUser->uuid,
            'type' => 'App\\Notifications\\StudentAttendanceRecorded',
        ]);

        Queue::assertPushed(SendFcmNotificationJob::class, function (SendFcmNotificationJob $job) use ($parentUser) {
            return $job->userUuid === $parentUser->uuid
                && $job->payload['type'] === 'absensi_siswa'
                && $job->payload['title'] === 'Anak sudah masuk sekolah'
                && str_contains($job->payload['message'], 'Budi Santoso')
                && str_contains($job->payload['message'], '07:12')
                && $job->payload['url'] === '/dashboard';
        });
    }

    public function test_scan_wajah_ulang_tidak_spam_notifikasi_orang_tua(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-13 07:12:00'));
        [$siswa, $kelas, $parentUser] = $this->siswaDenganOrangtua();

        $payload = [
            'id_siswa' => $siswa->uuid,
            'id_kelas' => $kelas->uuid,
            'tanggal' => '2026-07-13',
            'status' => 'hadir',
        ];

        $this->actingAs($this->admin())->postJson('/absensi/mark', $payload)->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-07-13 07:45:00'));
        $this->actingAs($this->admin())->postJson('/absensi/mark', $payload)->assertOk()->assertJson([
            'success' => true,
            'jam' => '07:12',
        ]);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $parentUser->uuid,
        ]);
        Queue::assertPushed(SendFcmNotificationJob::class, 1);
    }

    public function test_qr_geolocation_mengirim_notifikasi_ke_akun_orang_tua(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-13 07:20:00'));
        [$siswa, $kelas, $parentUser, $siswaUser] = $this->siswaDenganOrangtua();
        $token = $this->aktifkanQrGeolocation();

        $this->actingAs($siswaUser)->postJson('/absen-qr', [
            'token' => $token,
            'lat' => '-6.200000',
            'lng' => '106.816666',
        ])->assertOk()->assertJson([
            'ok' => true,
            'jam' => '07:20',
        ]);

        $this->assertDatabaseHas('absensis', [
            'id_siswa' => $siswa->uuid,
            'id_kelas' => $kelas->uuid,
            'tanggal' => '2026-07-13',
            'status' => 'hadir',
            'jam_masuk' => '07:20:00',
            'keterangan' => 'Absen QR',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $parentUser->uuid,
            'type' => 'App\\Notifications\\StudentAttendanceRecorded',
        ]);

        Queue::assertPushed(SendFcmNotificationJob::class, function (SendFcmNotificationJob $job) use ($parentUser) {
            return $job->userUuid === $parentUser->uuid
                && $job->payload['type'] === 'absensi_siswa'
                && str_contains($job->payload['message'], 'Budi Santoso')
                && str_contains($job->payload['message'], '07:20')
                && $job->payload['url'] === '/dashboard';
        });
    }

    public function test_qr_geolocation_ulang_tidak_spam_notifikasi_orang_tua(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-13 07:20:00'));
        [$siswa, $kelas, $parentUser, $siswaUser] = $this->siswaDenganOrangtua();
        $token = $this->aktifkanQrGeolocation();

        $payload = [
            'token' => $token,
            'lat' => '-6.200000',
            'lng' => '106.816666',
        ];

        $this->actingAs($siswaUser)->postJson('/absen-qr', $payload)->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-07-13 07:45:00'));
        $this->actingAs($siswaUser)->postJson('/absen-qr', $payload)
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $parentUser->uuid,
        ]);
        Queue::assertPushed(SendFcmNotificationJob::class, 1);
    }
}
