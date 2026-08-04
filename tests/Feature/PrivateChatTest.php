<?php

namespace Tests\Feature;

use App\Models\PrivateChatConversation;
use App\Notifications\PrivateChatMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

class PrivateChatTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    public function test_wali_dapat_membuka_private_chat_dari_nama_siswa(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $members = $this->actingAs($this->wali7a)
            ->getJson(route('grup.members', $grup))
            ->assertOk();

        $members->assertJsonFragment([
            'id' => $this->siswa7a->uuid,
            'private_chat_url' => route('grup.private.start', [$grup, $this->siswa7a]),
        ]);

        $this->actingAs($this->wali7a)
            ->get(route('grup.private.start', [$grup, $this->siswa7a]))
            ->assertRedirect();

        $conversation = PrivateChatConversation::firstOrFail();
        $this->assertTrue($conversation->includes($this->wali7a->uuid));
        $this->assertTrue($conversation->includes($this->siswa7a->uuid));
    }

    public function test_wali_dapat_membuka_private_chat_dengan_orang_tua_dari_paguyuban(): void
    {
        $grup = $this->grupPaguyuban($this->kelas7a);

        $this->actingAs($this->wali7a)
            ->get(route('grup.private.start', [$grup, $this->ortu7a]))
            ->assertRedirect();

        $conversation = PrivateChatConversation::firstOrFail();
        $this->assertTrue($conversation->includes($this->wali7a->uuid));
        $this->assertTrue($conversation->includes($this->ortu7a->uuid));
    }

    public function test_private_chat_bisa_dikirim_dan_mengirim_notifikasi_ke_penerima(): void
    {
        Notification::fake();
        $grup = $this->grupKelas($this->kelas7a);

        $this->actingAs($this->wali7a)->get(route('grup.private.start', [$grup, $this->siswa7a]));
        $conversation = PrivateChatConversation::firstOrFail();

        $this->actingAs($this->wali7a)
            ->postJson(route('private-chat.send', $conversation), ['body' => 'Halo, bagaimana kabarnya?'])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Halo, bagaimana kabarnya?');

        Notification::assertSentTo($this->siswa7a, PrivateChatMessageReceived::class);

        $this->actingAs($this->siswa7a)
            ->get(route('private-chat.show', $conversation))
            ->assertOk()
            ->assertSee('Halo, bagaimana kabarnya?');
    }

    public function test_wali_tidak_bisa_memulai_chat_dengan_siswa_kelas_lain(): void
    {
        $this->actingAs($this->wali7a)
            ->get(route('grup.private.start', [$this->grupKelas($this->kelas7a), $this->siswa7b]))
            ->assertForbidden();

        $this->assertDatabaseCount('private_chat_conversations', 0);
    }

    public function test_wali_lain_tidak_bisa_membaca_conversation_yang_bukan_relasi_kelasnya(): void
    {
        $this->actingAs($this->wali7a)->get(route('grup.private.start', [
            $this->grupKelas($this->kelas7a),
            $this->siswa7a,
        ]));
        $conversation = PrivateChatConversation::firstOrFail();

        $this->actingAs($this->wali7b)
            ->get(route('private-chat.show', $conversation))
            ->assertForbidden();
    }
}
