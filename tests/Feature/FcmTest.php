<?php

namespace Tests\Feature;

use App\Jobs\SendFcmNotificationJob;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Notifications\Channels\FcmChannel;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Tests\TestCase;

/**
 * Alur Firebase Cloud Messaging (FASE 9): registrasi/hapus token dari route
 * web (session+CSRF, bukan API terpisah), FcmChannel yang mendorong job ke
 * queue, dan SendFcmNotificationJob yang mengirim per-token dengan fail-safe
 * (token invalid dihapus, error lain hanya di-log, tak pernah melempar ke
 * pemanggil).
 */
class FcmTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access, string $username): User
    {
        // Bare user (tanpa profil Guru/Siswa) → lolos gate EnsureFaceRegistered
        // (pola sama seperti test suite lain: KeuanganSppTest, SarprasBookingTest).
        return User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access'   => $access,
        ]);
    }

    // ─────────────────────────── route registrasi/hapus token ───────────────────────────

    public function test_guest_tidak_bisa_registrasi_token_fcm(): void
    {
        $this->post('/notifications/fcm-token', ['token' => 'x'])
            ->assertRedirect(route('login'));
    }

    public function test_user_bisa_registrasi_token_fcm(): void
    {
        $user = $this->makeUser('guru', 'fcm_store_user');

        $this->actingAs($user)->postJson('/notifications/fcm-token', [
            'token'       => 'device-token-abc',
            'device_type' => 'android',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('user_fcm_tokens', [
            'user_uuid'   => $user->uuid,
            'token'       => 'device-token-abc',
            'device_type' => 'android',
        ]);
    }

    public function test_registrasi_tanpa_token_gagal_validasi(): void
    {
        $user = $this->makeUser('guru', 'fcm_store_novalid');

        $this->actingAs($user)->postJson('/notifications/fcm-token', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('token');
    }

    public function test_token_sama_berpindah_kepemilikan_saat_user_lain_registrasi(): void
    {
        $userA = $this->makeUser('guru', 'fcm_owner_a');
        $userB = $this->makeUser('guru', 'fcm_owner_b');

        $this->actingAs($userA)->postJson('/notifications/fcm-token', ['token' => 'shared-device-token'])->assertOk();
        $this->assertDatabaseHas('user_fcm_tokens', ['user_uuid' => $userA->uuid, 'token' => 'shared-device-token']);

        // Device yang sama dipakai login sebagai user B → token "pindah" tanpa duplikat (unique token).
        $this->actingAs($userB)->postJson('/notifications/fcm-token', ['token' => 'shared-device-token'])->assertOk();

        $this->assertSame(1, UserFcmToken::where('token', 'shared-device-token')->count());
        $this->assertDatabaseHas('user_fcm_tokens', ['user_uuid' => $userB->uuid, 'token' => 'shared-device-token']);
        $this->assertDatabaseMissing('user_fcm_tokens', ['user_uuid' => $userA->uuid, 'token' => 'shared-device-token']);
    }

    public function test_user_bisa_hapus_token_fcm_miliknya(): void
    {
        $user = $this->makeUser('guru', 'fcm_destroy_owner');
        UserFcmToken::create(['user_uuid' => $user->uuid, 'token' => 'to-be-removed']);

        $this->actingAs($user)->deleteJson('/notifications/fcm-token', ['token' => 'to-be-removed'])
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('user_fcm_tokens', ['token' => 'to-be-removed']);
    }

    public function test_hapus_token_tidak_menghapus_token_user_lain(): void
    {
        $userA = $this->makeUser('guru', 'fcm_destroy_a');
        $userB = $this->makeUser('guru', 'fcm_destroy_b');
        UserFcmToken::create(['user_uuid' => $userB->uuid, 'token' => 'milik-b']);

        // User A mencoba hapus token milik user B → query di-scope user_uuid, jadi tak terhapus.
        $this->actingAs($userA)->deleteJson('/notifications/fcm-token', ['token' => 'milik-b'])
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('user_fcm_tokens', ['user_uuid' => $userB->uuid, 'token' => 'milik-b']);
    }

    public function test_hapus_tanpa_body_token_tidak_error(): void
    {
        $user = $this->makeUser('guru', 'fcm_destroy_empty');

        $this->actingAs($user)->deleteJson('/notifications/fcm-token', [])
            ->assertOk()->assertJsonPath('ok', true);
    }

    // ─────────────────────────── FcmChannel ───────────────────────────

    public function test_fcm_channel_dispatch_job_saat_notification_punya_tofcm(): void
    {
        Queue::fake();
        $user = $this->makeUser('guru', 'fcm_channel_user');

        $notification = new class extends Notification {
            public function via($notifiable): array { return ['database']; }
            public function toFcm($notifiable): array
            {
                return ['title' => 'Judul Uji', 'message' => 'Pesan uji', 'url' => '/x', 'type' => 'uji'];
            }
        };

        (new FcmChannel())->send($user, $notification);

        Queue::assertPushed(SendFcmNotificationJob::class, function (SendFcmNotificationJob $job) use ($user) {
            return $job->userUuid === $user->uuid
                && $job->payload['title'] === 'Judul Uji'
                && $job->payload['type'] === 'uji';
        });
    }

    public function test_fcm_channel_tidak_dispatch_job_kalau_notification_tanpa_tofcm(): void
    {
        Queue::fake();
        $user = $this->makeUser('guru', 'fcm_channel_notofcm');

        $notification = new class extends Notification {
            public function via($notifiable): array { return ['database']; }
        };

        (new FcmChannel())->send($user, $notification);

        Queue::assertNothingPushed();
    }

    // ─────────────────────────── SendFcmNotificationJob ───────────────────────────

    public function test_job_selesai_tanpa_error_saat_fcm_disabled(): void
    {
        $user = $this->makeUser('guru', 'fcm_job_disabled');
        UserFcmToken::create(['user_uuid' => $user->uuid, 'token' => 'tok-disabled']);

        // Simulasikan FCM disabled tanpa bergantung pada ada/tidaknya file credential lokal.
        config(['services.firebase.credentials' => base_path('storage/app/firebase/not-found-service-account.json')]);
        $fcm = app(FcmService::class);
        $this->assertFalse($fcm->enabled());

        $job = new SendFcmNotificationJob($user->uuid, ['title' => 'T', 'message' => 'M', 'url' => '/', 'type' => 'x']);
        $job->handle($fcm); // tidak boleh melempar exception

        // Token tidak disentuh (job pulang lebih awal karena FCM belum aktif).
        $this->assertDatabaseHas('user_fcm_tokens', ['token' => 'tok-disabled']);
    }

    public function test_job_hapus_token_invalid_saat_kirim_gagal_notfound(): void
    {
        $user = $this->makeUser('guru', 'fcm_job_notfound');
        UserFcmToken::create(['user_uuid' => $user->uuid, 'token' => 'invalid-tok']);

        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('send')->once()->andThrow(NotFound::becauseTokenNotFound('invalid-tok'));
        });

        $job = new SendFcmNotificationJob($user->uuid, ['title' => 'T', 'message' => 'M', 'url' => '/', 'type' => 'x']);
        $job->handle(app(FcmService::class));

        $this->assertDatabaseMissing('user_fcm_tokens', ['token' => 'invalid-tok']);
    }

    public function test_job_hapus_token_invalid_saat_kirim_gagal_invalid_argument(): void
    {
        $user = $this->makeUser('guru', 'fcm_job_invalidarg');
        UserFcmToken::create(['user_uuid' => $user->uuid, 'token' => 'format-salah']);

        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('send')->once()->andThrow(new InvalidArgument('format token salah'));
        });

        $job = new SendFcmNotificationJob($user->uuid, ['title' => 'T', 'message' => 'M', 'url' => '/', 'type' => 'x']);
        $job->handle(app(FcmService::class));

        $this->assertDatabaseMissing('user_fcm_tokens', ['token' => 'format-salah']);
    }

    public function test_job_tidak_hapus_token_saat_gagal_karena_error_lain(): void
    {
        $user = $this->makeUser('guru', 'fcm_job_othererror');
        UserFcmToken::create(['user_uuid' => $user->uuid, 'token' => 'tok-network-fail']);

        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('Network timeout'));
        });

        $job = new SendFcmNotificationJob($user->uuid, ['title' => 'T', 'message' => 'M', 'url' => '/', 'type' => 'x']);
        $job->handle(app(FcmService::class)); // error jaringan/kuota → tidak boleh melempar ke pemanggil

        // Bukan token invalid → tidak dihapus, cuma di-log.
        $this->assertDatabaseHas('user_fcm_tokens', ['token' => 'tok-network-fail']);
    }

    public function test_job_kirim_ke_semua_token_meski_satu_invalid(): void
    {
        $user = $this->makeUser('guru', 'fcm_job_multi');
        UserFcmToken::create(['user_uuid' => $user->uuid, 'token' => 'tok-invalid']);
        UserFcmToken::create(['user_uuid' => $user->uuid, 'token' => 'tok-valid']);

        $sentTokens = [];
        $this->mock(FcmService::class, function ($mock) use (&$sentTokens) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('send')->twice()->andReturnUsing(function (string $token, array $payload) use (&$sentTokens) {
                $sentTokens[] = $token;
                if ($token === 'tok-invalid') {
                    throw NotFound::becauseTokenNotFound($token);
                }
            });
        });

        $job = new SendFcmNotificationJob($user->uuid, ['title' => 'T', 'message' => 'M', 'url' => '/', 'type' => 'x']);
        $job->handle(app(FcmService::class));

        sort($sentTokens);
        $this->assertSame(['tok-invalid', 'tok-valid'], $sentTokens); // kedua token tetap dicoba
        $this->assertDatabaseMissing('user_fcm_tokens', ['token' => 'tok-invalid']);
        $this->assertDatabaseHas('user_fcm_tokens', ['token' => 'tok-valid']);
    }
}
