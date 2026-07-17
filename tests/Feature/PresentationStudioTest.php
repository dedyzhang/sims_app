<?php

namespace Tests\Feature;

use App\Models\TeacherPresentation;
use App\Models\User;
use App\Support\PresentationSlides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PresentationStudioTest extends TestCase
{
    use RefreshDatabase;

    private function guru(): User
    {
        $guru = User::create([
            'username' => 'guru_presentasi',
            'password' => Hash::make('password'),
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);
        $guru->setGeminiApiKey('AIzaSyTestPersonalKeyForFeatureTests01');

        return $guru->fresh();
    }

    public function test_outline_parses_into_slides(): void
    {
        $slides = PresentationSlides::fromOutline("1. Judul\nPengantar\n2. Materi\n- poin A\n3. Penutup");

        $this->assertCount(3, $slides);
        $this->assertSame('Judul', $slides[0]['title']);
        $this->assertSame('Pengantar', $slides[0]['body']);
        $this->assertSame('Materi', $slides[1]['title']);
    }

    public function test_guru_can_create_and_present_in_studio(): void
    {
        $guru = $this->guru();

        $this->actingAs($guru)
            ->postJson(route('ai.teacher.presentasi.store'), [
                'title' => 'Fotosintesis',
                'subject' => 'IPA',
                'outline' => "1. Judul\n2. Proses\n3. Penutup",
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $item = TeacherPresentation::where('user_uuid', $guru->uuid)->first();
        $this->assertNotNull($item);
        $this->assertCount(3, $item->resolvedSlides());

        $this->actingAs($guru)
            ->get(route('ai.teacher.presentasi.show', $item))
            ->assertOk()
            ->assertSee('Studio Presentasi', false)
            ->assertSee('Presentasikan', false)
            ->assertSee('Canva Pendidikan', false);
    }

    public function test_presentasi_from_chat_creates_studio_item(): void
    {
        $guru = $this->guru();

        $this->actingAs($guru)
            ->postJson(route('ai.teacher.presentasi-from-chat'), [
                'title' => 'Dari Gemini',
                'outline' => "1. A\n2. B",
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('teacher_presentations', [
            'user_uuid' => $guru->uuid,
            'title' => 'Dari Gemini',
        ]);
    }

    public function test_asisten_guru_shows_studio_and_canva_without_chat_presentasi_send(): void
    {
        $guru = $this->guru();

        $this->actingAs($guru)
            ->get(route('ai.teacher.index'))
            ->assertOk()
            ->assertSee('Tanya Nalar Guru', false)
            ->assertSee('Canva Pendidikan', false)
            ->assertSee(route('ai.teacher.presentasi.index'), false)
            ->assertDontSee(route('ai.teacher.presentasi-from-chat'), false)
            ->assertDontSee('Kirim ke Presentasi', false);
    }

    public function test_guru_cannot_open_another_teachers_presentation(): void
    {
        $owner = $this->guru();
        $other = User::create([
            'username' => 'guru_presentasi_other',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $item = TeacherPresentation::create([
            'user_uuid' => $owner->uuid,
            'title' => 'Milik orang lain',
            'status' => 'draft',
            'outline' => "1. Satu\n2. Dua",
            'slides' => PresentationSlides::fromOutline("1. Satu\n2. Dua"),
        ]);

        $this->actingAs($other)
            ->get(route('ai.teacher.presentasi.show', $item))
            ->assertForbidden();

        $this->actingAs($other)
            ->get(route('ai.teacher.presentasi.pdf', $item))
            ->assertForbidden();

        $this->actingAs($other)
            ->putJson(route('ai.teacher.presentasi.update', $item), [
                'title' => 'Dihack',
                'outline' => '1. X',
            ])
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('ai.teacher.presentasi.destroy', $item))
            ->assertForbidden();

        $this->assertDatabaseHas('teacher_presentations', [
            'uuid' => $item->uuid,
            'title' => 'Milik orang lain',
        ]);
    }

    public function test_pdf_export(): void
    {
        $guru = $this->guru();
        $item = TeacherPresentation::create([
            'user_uuid' => $guru->uuid,
            'title' => 'Export PDF',
            'status' => 'draft',
            'outline' => "1. Satu\n2. Dua",
            'slides' => PresentationSlides::fromOutline("1. Satu\n2. Dua"),
        ]);

        $this->actingAs($guru)
            ->get(route('ai.teacher.presentasi.pdf', $item))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
