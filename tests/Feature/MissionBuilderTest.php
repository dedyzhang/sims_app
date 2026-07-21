<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Mission;
use App\Models\MissionStep;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MissionBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_can_create_mission_via_builder(): void
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        $guru = User::create([
            'username' => 'guru_builder',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $response = $this->actingAs($guru)->post(route('jagat-misi.builder.store'), [
            'title' => 'Misi Uji Builder',
            'subject' => 'IPA',
            'jenjang' => 'sd',
            'grade_detail' => 'Kelas 5',
            'mechanic_type' => 'recall_quiz_bundle',
            'duration_minutes' => 25,
            'summary' => 'Misi uji dari builder.',
            'reflections' => ['Apa yang kamu pelajari?'],
            'requires_reflection' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('missions', [
            'title' => 'Misi Uji Builder',
            'created_by' => $guru->uuid,
            'grade_level' => 'SD Kelas 5',
        ]);
        $mission = Mission::where('title', 'Misi Uji Builder')->first();
        $this->assertSame('sd', $mission->jenjangKey());
        $this->assertSame('SD', $mission->jenjangLabel());
        $this->assertDatabaseHas('mission_reflection_prompts', ['mission_id' => $mission->uuid]);
    }

    public function test_guru_can_publish_mission(): void
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        $guru = User::create([
            'username' => 'guru_pub',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $mission = Mission::factory()->recallQuiz()->create([
            'created_by' => $guru->uuid,
            'is_published' => false,
            'status' => 'draft',
        ]);

        MissionStep::create([
            'mission_id' => $mission->uuid,
            'module_key' => 'recall_quiz',
            'title' => 'Langkah 1',
            'prompt' => 'Jawab soal',
            'body' => 'Soal uji',
            'payload' => ['questions' => []],
            'max_points' => 10,
            'position' => 1,
        ]);

        $response = $this->actingAs($guru)->post(route('jagat-misi.builder.publish', $mission));
        $response->assertRedirect();
        $this->assertTrue($mission->fresh()->is_published);
    }

    public function test_publish_ditolak_jika_misi_tanpa_langkah(): void
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        $guru = User::create([
            'username' => 'guru_pub_empty',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $mission = Mission::factory()->recallQuiz()->create([
            'created_by' => $guru->uuid,
            'is_published' => false,
            'status' => 'draft',
        ]);

        $this->actingAs($guru)
            ->post(route('jagat-misi.builder.publish', $mission))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse($mission->fresh()->is_published);
    }

    public function test_guru_can_edit_shared_catalog_mission_without_owner(): void
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        $guru = User::create([
            'username' => 'guru_katalog',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $mission = Mission::factory()->recallQuiz()->create([
            'created_by' => null,
            'visible_to_teachers' => true,
            'is_published' => true,
            'status' => 'published',
            'slug' => 'katalog-shared-403-fix',
        ]);

        $this->actingAs($guru)
            ->get(route('jagat-misi.builder.edit', $mission))
            ->assertOk()
            ->assertSee($mission->title);
    }

    public function test_siswa_cannot_edit_catalog_mission(): void
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        $siswa = User::create([
            'username' => 'siswa_no_builder',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);

        $mission = Mission::factory()->recallQuiz()->create([
            'created_by' => null,
            'visible_to_teachers' => true,
            'slug' => 'katalog-siswa-forbidden',
        ]);

        $this->actingAs($siswa)
            ->get(route('jagat-misi.builder.edit', $mission))
            ->assertForbidden();
    }

    public function test_kurikulum_and_kepala_can_open_catalog_mission_play(): void
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);

        $mission = Mission::factory()->nalar()->create([
            'created_by' => null,
            'visible_to_teachers' => true,
            'is_published' => true,
            'status' => 'published',
            'slug' => 'katalog-play-staff-ok',
        ]);
        MissionStep::factory()->narrative()->create(['mission_id' => $mission->uuid]);

        foreach (['kurikulum', 'kepala', 'kesiswaan', 'walikelas', 'sapras', 'guru'] as $access) {
            $user = User::create([
                'username' => 'staff_'.$access,
                'password' => Hash::make('password'),
                'access' => $access,
            ]);

            $this->actingAs($user)
                ->get(route('jagat-misi.play', $mission))
                ->assertOk();
        }
    }

    public function test_sapras_merangkap_guru_boleh_builder_katalog(): void
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);

        $user = User::create([
            'username' => 'sapras_rangkap',
            'password' => Hash::make('password'),
            'access' => 'sapras',
        ]);
        Guru::create([
            'id_login' => $user->uuid,
            'nama' => 'Sapras Rangkap Guru',
            'nik' => '88001',
            'jk' => 'L',
            'face_descriptor' => [0.1],
        ]);

        $mission = Mission::factory()->recallQuiz()->create([
            'created_by' => null,
            'visible_to_teachers' => true,
            'is_published' => true,
            'status' => 'published',
            'slug' => 'katalog-sapras-builder',
        ]);

        $this->actingAs($user)
            ->get(route('jagat-misi.builder.index'))
            ->assertOk()
            ->assertSee('SD', false)
            ->assertSee('SMP', false)
            ->assertSee('SMA/SMK', false);

        $this->actingAs($user)
            ->get(route('jagat-misi.builder.edit', $mission))
            ->assertOk()
            ->assertSee($mission->title)
            ->assertSee('Jenjang pendidikan', false)
            ->assertSee('SMA/SMK', false);
    }
}
