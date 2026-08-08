<?php

namespace Tests\Feature;

use App\Jobs\SendFcmNotificationJob;
use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Notifications\SppBuktiDiunggahNotification;
use App\Notifications\SppPembayaranDiperbaruiNotification;
use App\Support\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KeuanganSppNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access, string $username): User
    {
        return User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access'   => $access,
        ]);
    }

    private function makeKelas(): Kelas
    {
        return Kelas::create(['tingkat' => '7', 'kelas' => 'A']);
    }

    private function makeSiswa(Kelas $kelas, ?User $login = null, int $spp = 150000, string $va = '8810999'): Siswa
    {
        return Siswa::create([
            'id_login' => $login?->uuid,
            'nama'     => 'Budi Notif',
            'nis'      => (string) random_int(10000, 99999),
            'id_kelas' => $kelas->uuid,
            'jk'       => 'L',
            'spp'      => (string) $spp,
            'va'       => $va,
        ]);
    }

    public function test_upload_bukti_mengirim_notifikasi_ke_bendahara(): void
    {
        Notification::fake();
        Storage::fake('local');

        $bendahara = $this->makeUser('bendahara', 'bendahara_notif_upload');
        $ortu = $this->makeUser('orangtua', 'ortu_notif_upload');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        $this->actingAs($ortu)->get('/tagihan-spp')->assertOk();
        $p = SppPembayaran::where('id_siswa', $siswa->uuid)->where('bulan', 1)->firstOrFail();

        $this->actingAs($ortu)->post('/tagihan-spp/'.$p->uuid.'/bukti', [
            'bank'  => 'BCA',
            'bukti' => UploadedFile::fake()->image('bukti.jpg', 600, 800),
        ])->assertRedirect();

        Notification::assertSentTo($bendahara, SppBuktiDiunggahNotification::class);
        Notification::assertNotSentTo($ortu, SppBuktiDiunggahNotification::class);
    }

    public function test_verifikasi_mengirim_notifikasi_ke_orang_tua(): void
    {
        Notification::fake();

        $bendahara = $this->makeUser('bendahara', 'bendahara_notif_verif');
        $ortu = $this->makeUser('orangtua', 'ortu_notif_verif');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid,
            'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 2,
            'nominal' => 150000,
            'status' => 'menunggu',
            'bank' => 'BCA',
            'tanggal_bayar' => now()->toDateString(),
        ]);

        $this->actingAs($bendahara)->post('/keuangan/verifikasi/verify', ['ids' => [$p->uuid]])->assertRedirect();

        Notification::assertSentTo(
            $ortu,
            SppPembayaranDiperbaruiNotification::class,
            fn (SppPembayaranDiperbaruiNotification $n) => $n->event === 'terverifikasi'
        );
    }

    public function test_validasi_lunas_mengirim_notifikasi_ke_orang_tua(): void
    {
        Notification::fake();

        $bendahara = $this->makeUser('bendahara', 'bendahara_notif_lunas');
        $ortu = $this->makeUser('orangtua', 'ortu_notif_lunas');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid,
            'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 3,
            'nominal' => 150000,
            'status' => 'terverifikasi',
            'bank' => 'BCA',
            'tanggal_bayar' => now()->toDateString(),
        ]);

        $this->actingAs($bendahara)->post('/keuangan/verifikasi/validate', ['ids' => [$p->uuid]])->assertRedirect();

        Notification::assertSentTo(
            $ortu,
            SppPembayaranDiperbaruiNotification::class,
            fn (SppPembayaranDiperbaruiNotification $n) => $n->event === 'lunas'
        );
    }

    public function test_tolak_mengirim_notifikasi_ke_orang_tua(): void
    {
        Notification::fake();

        $bendahara = $this->makeUser('bendahara', 'bendahara_notif_tolak');
        $ortu = $this->makeUser('orangtua', 'ortu_notif_tolak');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid,
            'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 4,
            'nominal' => 150000,
            'status' => 'menunggu',
            'bank' => 'BCA',
            'tanggal_bayar' => now()->toDateString(),
        ]);

        $this->actingAs($bendahara)->post('/keuangan/verifikasi/reject', [
            'ids' => [$p->uuid],
            'catatan' => 'Nominal tidak sesuai',
        ])->assertRedirect();

        Notification::assertSentTo(
            $ortu,
            SppPembayaranDiperbaruiNotification::class,
            fn (SppPembayaranDiperbaruiNotification $n) => $n->event === 'ditolak' && $n->catatan === 'Nominal tidak sesuai'
        );
    }

    public function test_upload_bukti_mendorong_push_fcm_ke_bendahara(): void
    {
        Queue::fake();
        Storage::fake('local');

        $bendahara = $this->makeUser('bendahara', 'bendahara_fcm_upload');
        UserFcmToken::create(['user_uuid' => $bendahara->uuid, 'token' => 'bendahara-device-token', 'device_type' => 'android']);

        $ortu = $this->makeUser('orangtua', 'ortu_fcm_upload');
        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        $this->actingAs($ortu)->get('/tagihan-spp')->assertOk();
        $p = SppPembayaran::where('id_siswa', $siswa->uuid)->where('bulan', 1)->firstOrFail();

        $this->actingAs($ortu)->post('/tagihan-spp/'.$p->uuid.'/bukti', [
            'bank'  => 'BCA',
            'bukti' => UploadedFile::fake()->image('bukti.jpg', 600, 800),
        ])->assertRedirect();

        Queue::assertPushed(SendFcmNotificationJob::class, function (SendFcmNotificationJob $job) use ($bendahara) {
            return $job->userUuid === $bendahara->uuid
                && $job->payload['type'] === 'spp_bukti_diunggah';
        });
    }

    public function test_verifikasi_mendorong_push_fcm_ke_orang_tua(): void
    {
        Queue::fake();

        $bendahara = $this->makeUser('bendahara', 'bendahara_fcm_verif');
        $ortu = $this->makeUser('orangtua', 'ortu_fcm_verif');
        UserFcmToken::create(['user_uuid' => $ortu->uuid, 'token' => 'ortu-device-token', 'device_type' => 'android']);

        $kelas = $this->makeKelas();
        $siswa = $this->makeSiswa($kelas);
        Orangtua::create(['id_login' => $ortu->uuid, 'id_siswa' => $siswa->uuid]);

        $p = SppPembayaran::create([
            'id_siswa' => $siswa->uuid,
            'tahun_ajaran' => TahunAjaran::current(),
            'bulan' => 5,
            'nominal' => 150000,
            'status' => 'menunggu',
            'bank' => 'BCA',
            'tanggal_bayar' => now()->toDateString(),
        ]);

        $this->actingAs($bendahara)->post('/keuangan/verifikasi/verify', ['ids' => [$p->uuid]])->assertRedirect();

        Queue::assertPushed(SendFcmNotificationJob::class, function (SendFcmNotificationJob $job) use ($ortu) {
            return $job->userUuid === $ortu->uuid
                && $job->payload['type'] === 'spp_pembayaran_status'
                && $job->payload['title'] === 'Bukti pembayaran diverifikasi';
        });
    }
}
