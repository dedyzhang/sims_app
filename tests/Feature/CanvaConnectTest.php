<?php

namespace Tests\Feature;

use App\Models\CanvaConnection;
use App\Models\Setting;
use App\Models\TeacherPresentation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CanvaConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.canva.client_id', 'canva-client-test');
        config()->set('services.canva.client_secret', 'canva-secret-test');
        config()->set('services.canva.redirect_uri', 'http://localhost/ai/teacher/canva/callback');
        config()->set('services.canva.allowed_email_suffix', '.belajar.id');
        config()->set('services.canva.api_base', 'https://api.canva.com/rest/v1');
        config()->set('services.canva.token_url', 'https://api.canva.com/rest/v1/oauth/token');
        config()->set('services.canva.auth_url', 'https://www.canva.com/api/oauth/authorize');
        config()->set('services.canva.revoke_url', 'https://api.canva.com/rest/v1/oauth/revoke');
        config()->set('services.canva.export_disk', 'local');
        config()->set('services.canva.export_poll_micros', 0);
        config()->set('services.canva.export_allowed_hosts', [
            'export-download.canva.com',
            'document-export.canva.com',
        ]);

        Setting::set('canva_connect_aktif', '1');
        Setting::set('canva_allowed_email_suffix', '.belajar.id');

        Storage::fake('local');
    }

    private function guru(?string $belajarId = 'guru@smp.belajar.id'): User
    {
        return User::create([
            'username' => 'guru-canva-'.uniqid(),
            'password' => 'password',
            'access' => 'guru',
            'canva_belajar_id' => $belajarId,
            'gemini_api_key' => Crypt::encryptString('AIzaSyTestPersonalKeyForFeatureTests01'),
            'gemini_api_key_hint' => 'ts01',
        ]);
    }

    public function test_callback_uses_sims_belajar_id_not_canva_profile_email(): void
    {
        $user = $this->guru('guru@smp.belajar.id');

        Http::fake([
            'https://api.canva.com/rest/v1/oauth/token' => Http::response([
                'access_token' => 'access-token-1',
                'refresh_token' => 'refresh-token-1',
                'expires_in' => 3600,
                'scope' => 'design:content:write profile:read',
            ], 200),
            // Bentuk API nyata: hanya team_user, tanpa email.
            'https://api.canva.com/rest/v1/users/me' => Http::response([
                'team_user' => [
                    'user_id' => 'canva-user-1',
                    'team_id' => 'team-1',
                ],
            ], 200),
            'https://api.canva.com/rest/v1/users/me/profile' => Http::response([
                'profile' => ['display_name' => 'Guru Canva'],
            ], 200),
        ]);

        $this->withSession([
            'canva_oauth' => [
                'state' => 'state-ok',
                'code_verifier' => 'verifier-ok',
                'user_uuid' => $user->uuid,
            ],
        ])->actingAs($user)
            ->get(route('ai.teacher.canva.callback', [
                'state' => 'state-ok',
                'code' => 'auth-code',
            ]))
            ->assertRedirect(route('ai.teacher.index'));

        $this->assertDatabaseHas('canva_connections', [
            'user_uuid' => $user->uuid,
            'email' => 'guru@smp.belajar.id',
            'canva_user_id' => 'canva-user-1',
        ]);
    }

    public function test_callback_rejects_without_sims_belajar_id(): void
    {
        $user = $this->guru(null);

        Http::fake([
            'https://api.canva.com/rest/v1/oauth/token' => Http::response([
                'access_token' => 'access-token-1',
                'refresh_token' => 'refresh-token-1',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->withSession([
            'canva_oauth' => [
                'state' => 'state-ok',
                'code_verifier' => 'verifier-ok',
                'user_uuid' => $user->uuid,
            ],
        ])->actingAs($user)
            ->get(route('ai.teacher.canva.callback', [
                'state' => 'state-ok',
                'code' => 'auth-code',
            ]))
            ->assertRedirect(route('ai.teacher.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('canva_connections', 0);
    }

    public function test_save_belajar_id_rejects_gmail(): void
    {
        $user = $this->guru(null);

        $this->actingAs($user)
            ->putJson(route('ai.teacher.canva.belajar-id'), [
                'canva_belajar_id' => 'guru@gmail.com',
            ])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->canva_belajar_id);
    }

    public function test_save_belajar_id_accepts_belajar_id(): void
    {
        $user = $this->guru(null);

        $this->actingAs($user)
            ->putJson(route('ai.teacher.canva.belajar-id'), [
                'canva_belajar_id' => 'Guru@SMP.Belajar.ID',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('canva.belajar_hint', 'guru@smp.belajar.id');

        $this->assertSame('guru@smp.belajar.id', $user->fresh()->canva_belajar_id);
    }

    public function test_setting_rejects_non_belajar_suffix(): void
    {
        $admin = User::create([
            'username' => 'admin-canva',
            'password' => 'password',
            'access' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('setting.integrasi'), [
                'tp_launcher_aktif' => '1',
                'canva_connect_aktif' => '1',
                'canva_allowed_email_suffix' => '.com',
            ])
            ->assertSessionHasErrors('canva_allowed_email_suffix');

        $this->assertNotSame('.com', Setting::get('canva_allowed_email_suffix'));
    }

    public function test_callback_rejects_invalid_state(): void
    {
        $user = $this->guru();

        $this->withSession([
            'canva_oauth' => [
                'state' => 'expected',
                'code_verifier' => 'verifier',
                'user_uuid' => $user->uuid,
            ],
        ])->actingAs($user)
            ->get(route('ai.teacher.canva.callback', [
                'state' => 'wrong',
                'code' => 'auth-code',
            ]))
            ->assertRedirect(route('ai.teacher.index'))
            ->assertSessionHas('error');
    }

    public function test_callback_rejects_when_feature_disabled(): void
    {
        Setting::set('canva_connect_aktif', '0');
        $user = $this->guru();

        $this->withSession([
            'canva_oauth' => [
                'state' => 'state-ok',
                'code_verifier' => 'verifier-ok',
                'user_uuid' => $user->uuid,
            ],
        ])->actingAs($user)
            ->get(route('ai.teacher.canva.callback', [
                'state' => 'state-ok',
                'code' => 'auth-code',
            ]))
            ->assertRedirect(route('ai.teacher.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('canva_connections', 0);
    }

    public function test_create_design_from_presentation(): void
    {
        $user = $this->guru();
        $this->seedConnection($user);

        $presentation = TeacherPresentation::create([
            'user_uuid' => $user->uuid,
            'title' => 'Fotosintesis',
            'status' => 'draft',
            'outline' => "1. Judul\nIsi",
        ]);

        Http::fake([
            'https://api.canva.com/rest/v1/designs' => Http::response([
                'design' => [
                    'id' => 'DAF123',
                    'title' => 'Fotosintesis',
                    'urls' => [
                        'edit_url' => 'https://www.canva.com/design/edit-test',
                        'view_url' => 'https://www.canva.com/design/view-test',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('ai.teacher.presentasi.canva.create', $presentation))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('design.id', 'DAF123');

        $presentation->refresh();
        $this->assertSame('DAF123', $presentation->canva_design_id);
        $this->assertSame('https://www.canva.com/design/edit-test', $presentation->canva_edit_url);
        $this->assertSame('in_progress', $presentation->status);
    }

    public function test_create_design_drops_non_canva_edit_url(): void
    {
        $user = $this->guru();
        $this->seedConnection($user);
        $presentation = TeacherPresentation::create([
            'user_uuid' => $user->uuid,
            'title' => 'Phish',
            'status' => 'draft',
        ]);

        Http::fake([
            'https://api.canva.com/rest/v1/designs' => Http::response([
                'design' => [
                    'id' => 'DAF999',
                    'urls' => [
                        'edit_url' => 'https://evil.example/phish',
                        'view_url' => 'https://www.canva.com/ok',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('ai.teacher.presentasi.canva.create', $presentation))
            ->assertOk()
            ->assertJsonPath('design.edit_url', null)
            ->assertJsonPath('design.view_url', 'https://www.canva.com/ok');
    }

    public function test_export_pdf_stores_on_private_disk(): void
    {
        $user = $this->guru();
        $this->seedConnection($user);

        $presentation = TeacherPresentation::create([
            'user_uuid' => $user->uuid,
            'title' => 'Export Test',
            'status' => 'in_progress',
            'canva_design_id' => 'DAF999',
        ]);

        Http::fake([
            'https://api.canva.com/rest/v1/exports' => Http::response([
                'job' => ['id' => 'job-1', 'status' => 'in_progress'],
            ], 200),
            'https://api.canva.com/rest/v1/exports/job-1' => Http::sequence()
                ->push(['job' => ['id' => 'job-1', 'status' => 'in_progress']], 200)
                ->push(['job' => [
                    'id' => 'job-1',
                    'status' => 'success',
                    'urls' => ['https://export-download.canva.com/file.pdf'],
                ]], 200),
            'https://export-download.canva.com/file.pdf' => Http::response("%PDF-1.4\ntest", 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('ai.teacher.presentasi.canva.export', $presentation))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('pages', 1)
            ->assertJsonPath('download', route('ai.teacher.presentasi.canva.download', $presentation));

        $presentation->refresh();
        $this->assertNotEmpty($presentation->canva_exported_pdf_path);
        Storage::disk('local')->assertExists($presentation->canva_exported_pdf_path);
        $this->assertStringStartsWith('canva-exports/'.$user->uuid.'/', $presentation->canva_exported_pdf_path);
    }

    public function test_export_multi_page_stores_zip(): void
    {
        $user = $this->guru();
        $this->seedConnection($user);
        $presentation = TeacherPresentation::create([
            'user_uuid' => $user->uuid,
            'title' => 'Multi',
            'status' => 'in_progress',
            'canva_design_id' => 'DAF888',
        ]);

        Http::fake([
            'https://api.canva.com/rest/v1/exports' => Http::response([
                'job' => ['id' => 'job-2', 'status' => 'success', 'urls' => [
                    'https://export-download.canva.com/p1.pdf',
                    'https://export-download.canva.com/p2.pdf',
                ]],
            ], 200),
            'https://api.canva.com/rest/v1/exports/job-2' => Http::response([
                'job' => ['id' => 'job-2', 'status' => 'success', 'urls' => [
                    'https://export-download.canva.com/p1.pdf',
                    'https://export-download.canva.com/p2.pdf',
                ]],
            ], 200),
            'https://export-download.canva.com/p1.pdf' => Http::response("%PDF-1.4\np1", 200),
            'https://export-download.canva.com/p2.pdf' => Http::response("%PDF-1.4\np2", 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('ai.teacher.presentasi.canva.export', $presentation))
            ->assertOk()
            ->assertJsonPath('pages', 2);

        $presentation->refresh();
        $this->assertStringEndsWith('.zip', $presentation->canva_exported_pdf_path);
        Storage::disk('local')->assertExists($presentation->canva_exported_pdf_path);
    }

    public function test_export_rejects_unsafe_download_host(): void
    {
        $user = $this->guru();
        $this->seedConnection($user);
        $presentation = TeacherPresentation::create([
            'user_uuid' => $user->uuid,
            'title' => 'SSRF',
            'status' => 'in_progress',
            'canva_design_id' => 'DAF777',
        ]);

        Http::fake([
            'https://api.canva.com/rest/v1/exports' => Http::response([
                'job' => ['id' => 'job-3', 'status' => 'success', 'urls' => ['http://127.0.0.1/secret']],
            ], 200),
            'https://api.canva.com/rest/v1/exports/job-3' => Http::response([
                'job' => ['id' => 'job-3', 'status' => 'success', 'urls' => ['http://127.0.0.1/secret']],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('ai.teacher.presentasi.canva.export', $presentation))
            ->assertStatus(422);

        $this->assertNull($presentation->fresh()->canva_exported_pdf_path);
    }

    public function test_owner_can_download_export(): void
    {
        $user = $this->guru();
        $path = 'canva-exports/'.$user->uuid.'/file.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\nok");

        $presentation = TeacherPresentation::create([
            'user_uuid' => $user->uuid,
            'title' => 'Download',
            'status' => 'in_progress',
            'canva_design_id' => 'DAF',
            'canva_exported_pdf_path' => $path,
        ]);

        $this->actingAs($user)
            ->get(route('ai.teacher.presentasi.canva.download', $presentation))
            ->assertOk();
    }

    public function test_refresh_url_updates_links(): void
    {
        $user = $this->guru();
        $this->seedConnection($user);
        $presentation = TeacherPresentation::create([
            'user_uuid' => $user->uuid,
            'title' => 'Refresh',
            'status' => 'in_progress',
            'canva_design_id' => 'DAF555',
        ]);

        Http::fake([
            'https://api.canva.com/rest/v1/designs/DAF555' => Http::response([
                'design' => [
                    'id' => 'DAF555',
                    'urls' => [
                        'edit_url' => 'https://www.canva.com/design/new-edit',
                        'view_url' => 'https://www.canva.com/design/new-view',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->postJson(route('ai.teacher.presentasi.canva.refresh', $presentation))
            ->assertOk()
            ->assertJsonPath('edit_url', 'https://www.canva.com/design/new-edit');

        $this->assertSame('https://www.canva.com/design/new-edit', $presentation->fresh()->canva_edit_url);
    }

    public function test_disconnect_clears_connection(): void
    {
        $user = $this->guru();
        $this->seedConnection($user);

        Http::fake([
            'https://api.canva.com/rest/v1/oauth/revoke' => Http::response([], 200),
        ]);

        $this->actingAs($user)
            ->deleteJson(route('ai.teacher.canva.disconnect'))
            ->assertOk()
            ->assertJsonPath('canva.connected', false);

        $this->assertDatabaseCount('canva_connections', 0);
    }

    public function test_feature_disabled_blocks_create(): void
    {
        Setting::set('canva_connect_aktif', '0');
        $user = $this->guru();
        $this->seedConnection($user);
        $presentation = TeacherPresentation::create([
            'user_uuid' => $user->uuid,
            'title' => 'Blocked',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->postJson(route('ai.teacher.presentasi.canva.create', $presentation))
            ->assertStatus(422);
    }

    public function test_outsider_cannot_export_others_presentation(): void
    {
        $owner = $this->guru();
        $outsider = $this->guru('lain@smp.belajar.id');
        $this->seedConnection($outsider);

        $presentation = TeacherPresentation::create([
            'user_uuid' => $owner->uuid,
            'title' => 'Milik Owner',
            'status' => 'draft',
            'canva_design_id' => 'DAF111',
        ]);

        $this->actingAs($outsider)
            ->postJson(route('ai.teacher.presentasi.canva.export', $presentation))
            ->assertForbidden();
    }

    public function test_require_connection_strips_non_belajar_email(): void
    {
        $user = $this->guru();
        CanvaConnection::create([
            'user_uuid' => $user->uuid,
            'canva_user_id' => 'x',
            'email' => 'guru@gmail.com',
            'display_name' => 'Bad',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => 'design:content:write',
            'connected_at' => now(),
        ]);

        $presentation = TeacherPresentation::create([
            'user_uuid' => $user->uuid,
            'title' => 'Strip',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->postJson(route('ai.teacher.presentasi.canva.create', $presentation))
            ->assertStatus(422);

        $this->assertDatabaseCount('canva_connections', 0);
    }

    private function seedConnection(User $user): void
    {
        CanvaConnection::create([
            'user_uuid' => $user->uuid,
            'canva_user_id' => 'canva-user',
            'email' => $user->canva_belajar_id ?: 'guru@smp.belajar.id',
            'display_name' => 'Guru',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => 'design:content:write profile:read',
            'connected_at' => now(),
        ]);
    }
}
