<?php

namespace Tests\Feature;

use App\Models\DemoAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoResetAndExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::fake();
        config([
            'demo.enabled' => true,
            'demo.hmac.secret' => 'sandbox-test-hmac-secret-32chars!',
        ]);
    }

    public function test_dry_run_tidak_menghapus_data(): void
    {
        $user = $this->operator();
        $this->insertNotification($user);
        $this->artisan('demo:reset-sandbox', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertTrue(User::query()->whereKey($user->uuid)->exists());
    }

    public function test_reset_menghapus_operasional_dan_menyimpan_akun(): void
    {
        $user = $this->operator();
        $access = DemoAccess::query()->create([
            'source_request_id' => (string) Str::uuid(),
            'school_display_name' => "Sekolah Demo B'tive",
            'contact_name' => 'Pemohon',
            'contact_email_encrypted' => 'enc',
            'contact_email_hash' => 'hash',
            'status' => DemoAccess::STATUS_ACTIVE,
            'starts_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
        $user->forceFill(['demo_access_id' => $access->id])->save();
        $this->insertNotification($user);

        $this->artisan('demo:reset-sandbox')->assertSuccessful();

        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertTrue(User::query()->whereKey($user->uuid)->exists());
        $this->assertTrue(DemoAccess::query()->whereKey($access->id)->exists());
        $this->assertSame($access->id, $user->fresh()->demo_access_id);
    }

    public function test_tabel_tidak_dikenal_fail_closed(): void
    {
        $user = $this->operator();
        $this->insertNotification($user);
        config(['demo.reset.truncate' => ['tabel_yang_tidak_ada']]);

        $this->artisan('demo:reset-sandbox')->assertFailed();
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertTrue(User::query()->whereKey($user->uuid)->exists());
    }

    public function test_tabel_terlarang_tidak_dihapus(): void
    {
        $user = $this->operator();
        config(['demo.reset.truncate' => ['users']]);

        $this->artisan('demo:reset-sandbox')->assertFailed();
        $this->assertTrue(User::query()->whereKey($user->uuid)->exists());
    }

    public function test_expire_idempotent_dan_menutup_akses(): void
    {
        $access = DemoAccess::query()->create([
            'source_request_id' => (string) Str::uuid(),
            'school_display_name' => "Sekolah Demo B'tive",
            'contact_name' => 'Pemohon',
            'contact_email_encrypted' => 'enc',
            'contact_email_hash' => 'hash',
            'status' => DemoAccess::STATUS_ACTIVE,
            'starts_at' => now()->subDays(5),
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('demo:expire-accesses')->assertSuccessful();
        $this->assertSame(DemoAccess::STATUS_EXPIRED, $access->fresh()->status);

        $this->artisan('demo:expire-accesses')->assertSuccessful();
        $this->assertSame(DemoAccess::STATUS_EXPIRED, $access->fresh()->status);
    }

    public function test_reset_ditolak_jika_sandbox_mati(): void
    {
        config(['demo.enabled' => false]);
        $this->artisan('demo:reset-sandbox')->assertFailed();
    }

    private function operator(): User
    {
        return User::create([
            'username' => 'operator_demo',
            'password' => Hash::make('password'),
            'access' => 'admin',
        ]);
    }

    private function insertNotification(User $user): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\PengumumanBaru',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->uuid,
            'data' => json_encode(['x' => 1]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
