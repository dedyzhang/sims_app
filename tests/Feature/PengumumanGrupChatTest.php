<?php

namespace Tests\Feature;

use App\Models\GrupChatMessage;
use App\Notifications\GrupChatDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

class PengumumanGrupChatTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    public function test_pengumuman_global_masuk_ke_semua_grup_dan_badge_penting(): void
    {
        $this->actingAs($this->admin)
            ->post('/pengumuman', [
                'judul' => 'Libur Sekolah',
                'isi' => 'Sekolah libur besok.',
            ])
            ->assertRedirect();

        $this->assertSame(4, GrupChatMessage::count());
        $this->assertDatabaseHas('grup_chat_messages', [
            'sender_peran' => 'admin',
            'body' => "[PENGUMUMAN PENTING]\nLibur Sekolah\n\nSekolah libur besok.",
        ]);

        // Jalur chat siswa tetap menerima pesan, dan badge grup naik.
        $this->actingAs($this->siswa7a)
            ->getJson(route('grup.badge'))
            ->assertOk()
            ->assertJsonPath('unread', 1);

        $this->actingAs($this->siswa7a)
            ->getJson(route('grup.poll', $this->grupKelas($this->kelas7a)).'?after=0')
            ->assertOk()
            ->assertJsonPath('messages.0.body', "[PENGUMUMAN PENTING]\nLibur Sekolah\n\nSekolah libur besok.");

        // Jalur bell tetap menandai pengumuman global sebagai penting/unread.
        $this->actingAs($this->siswa7a)
            ->getJson(route('notifications.json'))
            ->assertOk()
            ->assertJsonPath('unreadPengumuman', 1);

        $this->actingAs($this->siswa7a)
            ->get(route('pengumuman.index'))
            ->assertOk()
            ->assertSee('Penting');
    }

    public function test_digest_grup_chat_terkirim_untuk_pengumuman_global(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post('/pengumuman', [
            'judul' => 'Pengumuman Penting',
            'isi' => 'Mohon dibaca.',
        ])->assertRedirect();

        $this->artisan('grupchat:kirim-notif')->assertSuccessful();

        Notification::assertSentTo($this->siswa7a, GrupChatDigestNotification::class);
        Notification::assertSentTo($this->ortu7a, GrupChatDigestNotification::class);
    }

    public function test_pengumuman_bertarget_peran_tidak_mengirim_ke_grup_umum(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post('/pengumuman', [
            'judul' => 'Rapat Guru',
            'isi' => 'Khusus guru.',
            'target_roles' => ['guru'],
        ])->assertRedirect();

        $this->assertDatabaseCount('grup_chat_messages', 0);
    }
}
