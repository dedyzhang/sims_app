<?php

namespace Tests\Feature;

use App\Models\DemoAccess;
use App\Models\Guru;
use App\Models\Setting;
use App\Models\User;
use App\Support\DemoHmac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DemoProvisioningSandboxTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sandbox-test-hmac-secret-32chars!';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'demo.enabled' => true,
            'demo.hmac.secret' => self::SECRET,
            'demo.hmac.key_id' => 'landing-v1',
            'demo.landing_callback_url' => '',
        ]);
        Notification::fake();
        Http::fake();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Sekolah Demo']);
    }

    public function test_lima_retry_identik_hanya_membuat_satu_akses(): void
    {
        $payload = $this->payload();
        $idempotency = (string) Str::uuid();
        $statuses = [];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->signedPost('/internal/v1/demo/provisions', $payload, $idempotency);
            $statuses[] = $response->status();
            $response->assertJsonMissingPath('data.password');
            $this->assertStringNotContainsStringIgnoringCase('password', $response->getContent());
        }

        $this->assertSame(201, $statuses[0]);
        $this->assertSame([200, 200, 200, 200], array_slice($statuses, 1));
        $this->assertSame(1, DemoAccess::query()->count());
        $this->assertSame(5, User::query()->whereNotNull('demo_access_id')->count());
        $this->assertSame($createdId = $this->jsonId($this->signedPost('/internal/v1/demo/provisions', $payload, $idempotency)), DemoAccess::query()->value('id'));
        $this->assertNotNull($createdId);
    }

    public function test_role_tidak_dikenal_ditolak_sebelum_transaksi(): void
    {
        $payload = $this->payload();
        $payload['accounts'] = [['role' => 'superadmin', 'count' => 1]];

        $this->signedPost('/internal/v1/demo/provisions', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DEMO_ROLE_INVALID');

        $this->assertSame(0, DemoAccess::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Guru::query()->count());
    }

    public function test_signature_salah_dan_nonce_ulang_ditolak(): void
    {
        $payload = $this->payload();
        $nonce = (string) Str::uuid();
        $this->signedPost('/internal/v1/demo/provisions', $payload, nonce: $nonce)->assertCreated();

        $this->signedPost('/internal/v1/demo/provisions', $payload, nonce: $nonce)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'SIGNATURE_INVALID');

        $this->callSigned('POST', '/internal/v1/demo/provisions', $payload, signature: 'sha256='.str_repeat('0', 64))
            ->assertStatus(401);
    }

    public function test_idempotency_body_berbeda_menghasilkan_konflik(): void
    {
        $payload = $this->payload();
        $key = (string) Str::uuid();
        $this->signedPost('/internal/v1/demo/provisions', $payload, $key)->assertCreated();

        $payload['duration_hours'] = 48;
        $this->signedPost('/internal/v1/demo/provisions', $payload, $key)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_user_demo_bisa_login_tanpa_wajah_dan_mutasi_sensitif_ditolak(): void
    {
        $payload = $this->payload();
        $created = $this->signedPost('/internal/v1/demo/provisions', $payload)->assertCreated();
        $user = User::query()->where('access', 'guru')->whereNotNull('demo_access_id')->firstOrFail();

        $this->post('/login', [
            'credential' => $user->username,
            'password' => 'not-the-real-password',
        ])->assertSessionHasErrors('credential');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();

        $this->actingAs($user)
            ->putJson('/profile/update', ['username' => 'hacked'])
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect();

        $normal = User::create([
            'username' => 'guru_produksi',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        Guru::create([
            'id_login' => $normal->uuid,
            'nama' => 'Guru Produksi',
            'nik' => 'PROD01',
            'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);

        $status = $this->actingAs($normal)->post('/dashboard/tata-letak', ['layout' => []])->status();
        $this->assertNotSame(403, $status);
    }

    /**
     * Pemeriksa Soal memanggil Gemini lewat InteractsWithAi dan tagihannya nyata.
     * Nama route-nya 'classroom.arena.quality-checker.*' — ikut ter-allow oleh prefix
     * lebar 'classroom.' di demo.mutation_allow, sementara daftar deny hanya memuat
     * 'ai.'/'asisten.'. Tanpa entri deny yang tepat, akun demo anonim bisa membakar
     * kuota AI sekolah walaupun DEMO_INTEGRATIONS_ENABLED=false.
     */
    /**
     * Pencabutan bersifat final. POST ulang dgn source_request_id yang sama + key
     * idempotensi baru tidak boleh menghidupkan kembali demo yang sengaja dimatikan.
     */
    public function test_akses_yang_dicabut_tidak_bisa_dihidupkan_ulang(): void
    {
        $payload = $this->payload();
        $this->signedPost('/internal/v1/demo/provisions', $payload)->assertCreated();
        $access = DemoAccess::query()->firstOrFail();

        $this->signedPost('/internal/v1/demo/accesses/'.$access->id.'/revoke', [])->assertOk();
        $this->assertSame(DemoAccess::STATUS_REVOKED, $access->fresh()->status);

        $this->signedPost('/internal/v1/demo/provisions', $payload, (string) Str::uuid())
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEMO_ACCESS_REVOKED');

        $this->assertSame(DemoAccess::STATUS_REVOKED, $access->fresh()->status);
        $this->assertSame(1, DemoAccess::query()->count());
    }

    /**
     * Resend merotasi password SEBELUM email dikirim. Kalau pengiriman gagal, satu-
     * satunya salinan plaintext hilang — tanpa rollback setiap akun demo terkunci
     * permanen, dan pemanggil tak punya sinyal apa pun kalau kita balas 200/"sent".
     */
    public function test_resend_yang_gagal_kirim_tidak_mengunci_akun_demo(): void
    {
        $this->signedPost('/internal/v1/demo/provisions', $this->payload())->assertCreated();
        $access = DemoAccess::query()->firstOrFail();

        $hashSebelum = User::query()->whereNotNull('demo_access_id')
            ->orderBy('username')->pluck('password', 'username')->all();

        Notification::shouldReceive('route')->andThrow(new \RuntimeException('SMTP mati'));

        $this->signedPost('/internal/v1/demo/accesses/'.$access->id.'/resend-onboarding', [])
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'DEMO_ONBOARDING_FAILED');

        $hashSesudah = User::query()->whereNotNull('demo_access_id')
            ->orderBy('username')->pluck('password', 'username')->all();

        $this->assertSame($hashSebelum, $hashSesudah, 'Password lama harus dipulihkan saat email gagal terkirim.');
    }

    public function test_akun_demo_tak_bisa_memicu_pemeriksa_soal_ai(): void
    {
        $this->signedPost('/internal/v1/demo/provisions', $this->payload())->assertCreated();
        $user = User::query()->where('access', 'guru')->whereNotNull('demo_access_id')->firstOrFail();

        $middleware = new \App\Http\Middleware\RestrictDemoMutations;
        $ditolak = fn (string $route) => (new \ReflectionMethod($middleware, 'denied'))->invoke($middleware, $route);

        $this->assertTrue($ditolak('classroom.arena.quality-checker.check'));
        $this->assertTrue($ditolak('classroom.arena.quality-checker.batch'));
        $this->assertTrue($ditolak('ai.chat'));

        // Mutasi Ruang Kelas biasa tetap boleh — demo harus tetap terasa utuh.
        $this->assertFalse($ditolak('classroom.material.store'));

        $this->assertNotNull($user->demo_access_id);
    }

    public function test_akses_kedaluwarsa_menolak_login_baru(): void
    {
        $this->signedPost('/internal/v1/demo/provisions', $this->payload())->assertCreated();
        $access = DemoAccess::query()->firstOrFail();
        $access->forceFill([
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subMinute(),
        ])->save();
        $user = $access->users()->firstOrFail();

        $user->password = Hash::make('secret123');
        $user->save();

        $this->post('/login', [
            'credential' => $user->username,
            'password' => 'secret123',
        ])->assertRedirect(route('demo.berakhir'));

        $this->assertGuest();
    }

    public function test_sandbox_mati_mengembalikan_503(): void
    {
        config(['demo.enabled' => false]);
        $this->signedPost('/internal/v1/demo/provisions', $this->payload())
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'DEMO_TEMPORARILY_UNAVAILABLE');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedPost(string $path, array $payload, ?string $idempotency = null, ?string $nonce = null): TestResponse
    {
        return $this->callSigned('POST', $path, $payload, $idempotency, $nonce);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function callSigned(
        string $method,
        string $path,
        array $payload,
        ?string $idempotency = null,
        ?string $nonce = null,
        ?string $signature = null,
    ): TestResponse {
        $body = DemoHmac::encodeBody($payload);
        $timestamp = (string) time();
        $nonce ??= (string) Str::uuid();
        $idempotency ??= (string) Str::uuid();
        $canonical = DemoHmac::canonicalString($method, $path, $timestamp, $nonce, $idempotency, DemoHmac::bodyHash($body));
        $signature ??= DemoHmac::signature($canonical, self::SECRET);

        return $this->call($method, $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIMS_KEY_ID' => 'landing-v1',
            'HTTP_X_SIMS_TIMESTAMP' => $timestamp,
            'HTTP_X_SIMS_NONCE' => $nonce,
            'HTTP_X_SIMS_IDEMPOTENCY_KEY' => $idempotency,
            'HTTP_X_SIMS_CORRELATION_ID' => (string) Str::uuid(),
            'HTTP_X_SIMS_SIGNATURE' => $signature,
        ], $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'source_request_id' => '4b63d11a-5c67-4b67-88e0-1319d420be44',
            'school_display_name' => "Sekolah Demo B'tive",
            'contact' => [
                'name' => 'Kepala Sekolah',
                'email' => 'kepala@example.sch.id',
            ],
            'duration_hours' => 120,
            'accounts' => [
                ['role' => 'kepala', 'count' => 1],
                ['role' => 'admin', 'count' => 1],
                ['role' => 'guru', 'count' => 3],
            ],
            'focus_modules' => ['akademik', 'absensi'],
            'approved_by' => 'operator-test',
        ];
    }

    private function jsonId(TestResponse $response): ?string
    {
        return $response->json('data.demo_access_id');
    }
}
