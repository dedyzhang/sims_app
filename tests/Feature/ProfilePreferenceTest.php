<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePreferenceTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\DataProvider('dashboardThemes')]
    public function test_user_can_save_dashboard_theme_preference(string $theme): void
    {
        $user = User::create([
            'username' => 'theme_admin',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);

        $this->actingAs($user)->get(route('profile.preference'))->assertOk()->assertSee('macOS Minimal')->assertSee('Bento');

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
            'dashboard_theme' => $theme,
            'font_size' => 'md',
            'compact_mode' => false,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->actingAs($user->fresh())->get(route('profile.preference'))
            ->assertOk()->assertSee('data-ui-theme="'.$theme.'"', false)
            ->assertSee('Wallpaper pribadi')->assertSee('sims-appearance.js');

        $this->assertDatabaseHas('user_preferences', [
            'user_uuid' => $user->uuid,
            'dashboard_theme' => $theme,
        ]);
    }
    public static function dashboardThemes(): array
    {
        return array_map(fn ($theme) => [$theme], ['windows11', 'macos', 'fluent', 'macos-minimal', 'bento']);
    }

    public function test_unknown_dashboard_theme_is_rejected(): void
    {
        $user = User::create(['username' => 'invalid_theme', 'password' => Hash::make('password'), 'access' => 'superadmin']);
        $this->actingAs($user)->putJson(route('profile.preference.update'), ['dashboard_theme' => 'unknown'])
            ->assertUnprocessable()->assertJsonValidationErrors('dashboard_theme');
    }
}