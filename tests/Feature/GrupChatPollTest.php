<?php

namespace Tests\Feature;

use App\Models\GrupChatMember;
use App\Models\GrupChatMessage;
use App\Services\GrupChatMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

class GrupChatPollTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    public function test_kirim_pesan_menaikkan_seq_dan_denormal_grup(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'Besok bawa buku paket.'])
            ->assertCreated()
            ->assertJsonPath('message.seq', 1)
            ->assertJsonPath('message.body', 'Besok bawa buku paket.');

        $grup->refresh();
        $this->assertSame(1, (int) $grup->last_seq);
        $this->assertSame('Besok bawa buku paket.', $grup->last_message_preview);
        $this->assertSame('Bu Ani', $grup->last_message_by);
    }

    public function test_seq_unik_dan_berurutan_per_grup(): void
    {
        $a = $this->grupKelas($this->kelas7a);
        $b = $this->grupKelas($this->kelas7b);

        foreach (['satu', 'dua', 'tiga'] as $teks) {
            $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $a), ['body' => $teks]);
        }
        $this->actingAs($this->wali7b)->postJson(route('grup.pesan', $b), ['body' => 'halo']);

        $this->assertSame([1, 2, 3], GrupChatMessage::where('grup_id', $a->uuid)
            ->orderBy('seq')->pluck('seq')->map(fn ($s) => (int) $s)->all());

        // Counter berdiri sendiri per grup — grup lain tetap mulai dari 1.
        $this->assertSame([1], GrupChatMessage::where('grup_id', $b->uuid)
            ->pluck('seq')->map(fn ($s) => (int) $s)->all());
    }

    public function test_poll_mengembalikan_pesan_setelah_cursor(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        foreach (['p1', 'p2', 'p3'] as $teks) {
            $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => $teks]);
        }

        $this->actingAs($this->siswa7a)
            ->getJson(route('grup.poll', $grup).'?after=1')
            ->assertOk()
            ->assertJsonPath('last_seq', 3)
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.0.body', 'p2')
            ->assertJsonPath('messages.1.body', 'p3');
    }

    public function test_poll_tanpa_pesan_baru_mengembalikan_array_kosong(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'satu']);

        // Jalur cepat: cursor sudah menyamai last_seq → tabel pesan tidak disentuh.
        $this->actingAs($this->siswa7a)
            ->getJson(route('grup.poll', $grup).'?after=1')
            ->assertOk()
            ->assertJsonPath('last_seq', 1)
            ->assertJsonCount(0, 'messages');
    }

    public function test_poll_backlog_besar_mengembalikan_cursor_batch_agar_pesan_tidak_terlewati(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        for ($seq = 1; $seq <= 201; $seq++) {
            GrupChatMessage::create([
                'grup_id' => $grup->uuid,
                'seq' => $seq,
                'user_id' => $this->wali7a->uuid,
                'sender_nama' => 'Bu Ani',
                'sender_peran' => 'walikelas',
                'body' => "pesan {$seq}",
            ]);
        }
        $grup->update(['last_seq' => 201]);

        $this->actingAs($this->siswa7a)
            ->getJson(route('grup.poll', $grup).'?after=0')
            ->assertOk()
            ->assertJsonCount(200, 'messages')
            ->assertJsonPath('messages.0.seq', 1)
            ->assertJsonPath('messages.199.seq', 200)
            ->assertJsonPath('next_after', 200)
            ->assertJsonPath('last_seq', 201);

        $this->actingAs($this->siswa7a)
            ->getJson(route('grup.poll', $grup).'?after=200')
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.seq', 201)
            ->assertJsonPath('next_after', 201);
    }

    public function test_riwayat_lama_dapat_dimuat_bertahap_dan_tetap_mematuhi_batas_anggota(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        for ($seq = 1; $seq <= 55; $seq++) {
            GrupChatMessage::create([
                'grup_id' => $grup->uuid,
                'seq' => $seq,
                'user_id' => $this->wali7a->uuid,
                'sender_nama' => 'Bu Ani',
                'sender_peran' => 'walikelas',
                'body' => "pesan {$seq}",
            ]);
        }
        $grup->update(['last_seq' => 55]);

        $this->actingAs($this->siswa7a)
            ->getJson(route('grup.pesan.lama', $grup).'?before=6')
            ->assertOk()
            ->assertJsonCount(5, 'messages')
            ->assertJsonPath('messages.0.seq', 1)
            ->assertJsonPath('messages.4.seq', 5)
            ->assertJsonPath('next_before', 1)
            ->assertJsonPath('has_more', false);

        // Anggota baru tidak boleh memakai endpoint ini untuk membaca pesan sebelum
        // joined_seq, walaupun cursor URL dimundurkan ke awal percakapan.
        [$userBaru, $profilBaru] = $this->buatSiswa($this->kelas7a, 'siswa_lama_test', 'Dodi', 'NISLAMA');
        app(\App\Services\GrupChatService::class)->syncSiswa($profilBaru);

        $this->actingAs($userBaru)
            ->getJson(route('grup.pesan.lama', $grup).'?before=1')
            ->assertOk()
            ->assertJsonCount(0, 'messages');
    }

    public function test_poll_menandai_terbaca_dan_menghapus_badge(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'halo']);

        $this->actingAs($this->siswa7a)->getJson(route('grup.badge'))
            ->assertOk()->assertJsonPath('unread', 1);

        $this->actingAs($this->siswa7a)->getJson(route('grup.poll', $grup).'?after=0')->assertOk();

        $this->actingAs($this->siswa7a)->getJson(route('grup.badge'))
            ->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_membaca_juga_memajukan_watermark_notifikasi(): void
    {
        // Anti-spam FCM: user yang sedang membuka grup tidak boleh menghasilkan push.
        $grup = $this->grupKelas($this->kelas7a);
        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'halo']);
        $this->actingAs($this->siswa7a)->getJson(route('grup.poll', $grup).'?after=0');

        $member = GrupChatMember::where('grup_id', $grup->uuid)
            ->where('user_id', $this->siswa7a->uuid)->first();

        $this->assertSame(1, (int) $member->last_notified_seq);
    }

    public function test_badge_tidak_menghitung_pesan_sendiri(): void
    {
        $grup = $this->grupKelas($this->kelas7a);
        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'dari wali']);

        $this->actingAs($this->wali7a)->getJson(route('grup.badge'))
            ->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_siswa_tidak_melihat_pesan_sebelum_bergabung_tapi_walikelas_melihat_semua(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        // Percakapan berjalan sebelum siswa baru masuk kelas.
        foreach (['lama1', 'lama2'] as $teks) {
            $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => $teks]);
        }

        [$userBaru, $profilBaru] = $this->buatSiswa($this->kelas7a, 'siswa_baru', 'Cici', 'NISBRU');
        app(\App\Services\GrupChatService::class)->syncSiswa($profilBaru);

        $this->actingAs($this->wali7a)->postJson(route('grup.pesan', $grup), ['body' => 'baru1']);

        $this->actingAs($userBaru)
            ->getJson(route('grup.poll', $grup).'?after=0')
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'baru1');

        // Staf tetap melihat riwayat penuh — syarat agar moderasi mungkin dilakukan.
        $this->actingAs($this->wali7a)
            ->getJson(route('grup.poll', $grup).'?after=0')
            ->assertOk()
            ->assertJsonCount(3, 'messages');
    }

    public function test_pesan_kosong_ditolak(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), ['body' => '   '])
            ->assertStatus(422);

        $this->assertDatabaseCount('grup_chat_messages', 0);
    }

    public function test_pesan_terlalu_panjang_ditolak(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $this->actingAs($this->wali7a)
            ->postJson(route('grup.pesan', $grup), [
                'body' => str_repeat('a', GrupChatMessenger::MAX_BODY + 1),
            ])
            ->assertStatus(422);
    }

    public function test_nama_pengirim_disnapshot_bukan_dihitung_ulang(): void
    {
        $grup = $this->grupPaguyuban($this->kelas7a);
        $this->actingAs($this->ortu7a)->postJson(route('grup.pesan', $grup), ['body' => 'terima kasih bu']);

        $pesan = GrupChatMessage::first();
        $this->assertSame('Ortu Andi', $pesan->sender_nama);

        // Nama anak berubah — pesan lama tetap memakai snapshot saat dikirim.
        $this->profilSiswa7a->update(['nama' => 'Andi Baru']);
        $this->assertSame('Ortu Andi', $pesan->fresh()->sender_nama);
    }
}
