<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarprasRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonicalize_maps_sapras_to_sarpras(): void
    {
        $this->assertSame('sarpras', UserRole::canonicalize('sapras'));
        $this->assertSame('sarpras', UserRole::canonicalize('sarpras'));
        $this->assertTrue(UserRole::matches('sapras', 'sarpras'));
        $this->assertTrue(UserRole::matches('sarpras', 'sapras'));
    }

    public function test_saving_user_normalizes_sapras_access(): void
    {
        $user = User::create([
            'username' => 'waka_sapras_alias',
            'password' => Hash::make('password'),
            'access' => 'sapras',
        ]);

        $this->assertSame('sarpras', $user->fresh()->access);
    }

    public function test_legacy_sapras_string_still_opens_asisten_guru_via_middleware(): void
    {
        // Simulasikan baris lama yang belum di-normalize di DB.
        $user = User::create([
            'username' => 'waka_sapras_raw',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        \Illuminate\Support\Facades\DB::table('users')->where('uuid', $user->uuid)->update(['access' => 'sapras']);
        $user = $user->fresh();

        $this->assertSame('sapras', $user->access);

        $this->actingAs($user)
            ->get(route('ai.teacher.index'))
            ->assertOk();
    }

    public function test_canonical_sarpras_opens_asisten_guru(): void
    {
        $user = User::create([
            'username' => 'waka_sarpras',
            'password' => Hash::make('password'),
            'access' => 'sarpras',
            'gemini_api_key' => \Illuminate\Support\Facades\Crypt::encryptString('AIzaSyTestPersonalKeyForFeatureTests01'),
            'gemini_api_key_hint' => 'ts01',
        ]);

        $this->actingAs($user)
            ->get(route('ai.teacher.index'))
            ->assertOk();
    }
}
