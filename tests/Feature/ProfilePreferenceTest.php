<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_dashboard_theme_preference(): void
    {
        $user = User::create([
            'username' => 'theme_admin',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);

        $response = $this->actingAs($user)->putJson(route('profile.preference.update'), [
            'primary_color' => '#2563eb',
            'secondary_color' => '#3b82f6',
            'accent_color' => '#f59e0b',
            'sidebar_style' => 'default',
            'sidebar_bg' => '#ffffff',
            'sidebar_text' => '#475569',
            'theme_mode' => 'light',
            'motif' => 'minimal',
            'ui_style' => 'soft',
            'dashboard_theme' => 'macos',
            'font_size' => 'md',
            'compact_mode' => false,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_preferences', [
            'user_uuid' => $user->uuid,
            'dashboard_theme' => 'macos',
        ]);
    }
}