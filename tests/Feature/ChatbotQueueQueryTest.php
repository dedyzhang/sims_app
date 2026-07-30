<?php

namespace Tests\Feature;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ChatbotAdminController::queue() — polling inbox admin (tiap `chatbot.poll_interval_seconds`
 * detik, default 5) — dulu memanggil summarize() PER percakapan yg masing2 menjalankan 2 query
 * terpisah (pesan terakhir + hitung belum-dibaca) LEWAT $c->messages(), plus lazy-load
 * guru/siswa per user via displayName(). Dgn puluhan percakapan menunggu, itu jadi 3-4+
 * query x N percakapan, SETIAP 5 DETIK — N+1 terbesar yg ditemukan dlm audit query sesi ini.
 * Test ini mengunci jumlah query TIDAK naik seiring bertambahnya percakapan dlm antrian,
 * dan hasil ringkasan (pesan terakhir, jumlah belum-dibaca) tetap benar.
 */
class ChatbotQueueQueryTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access, string $username): User
    {
        return User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access' => $access,
        ]);
    }

    private function makeWaitingConversation(string $username): ChatbotConversation
    {
        $siswa = $this->makeUser('siswa', $username);
        $this->actingAs($siswa)->postJson('/chatbot/send', ['message' => 'halo dari ' . $username])->assertOk();
        $conversation = ChatbotConversation::where('user_id', $siswa->getKey())->firstOrFail();
        $this->actingAs($siswa)->postJson("/chatbot/{$conversation->id}/request-human")->assertOk();

        return $conversation->fresh();
    }

    public function test_queue_mengembalikan_pesan_terakhir_dan_unread_count_yg_benar(): void
    {
        $admin = $this->makeUser('admin', 'admin_queue_correct');

        $c1 = $this->makeWaitingConversation('siswa_q1');
        $c2 = $this->makeWaitingConversation('siswa_q2');

        $resp = $this->actingAs($admin)->getJson('/chatbot/admin/queue')->assertOk();
        $rows = collect($resp->json('conversations'))->keyBy('id');

        $expectedLast1 = ChatbotMessage::where('conversation_id', $c1->id)->latest('created_at')->first();
        $expectedLast2 = ChatbotMessage::where('conversation_id', $c2->id)->latest('created_at')->first();
        $expectedUnread1 = ChatbotMessage::where('conversation_id', $c1->id)
            ->where('sender', 'user')->whereNull('read_at')->count();

        $this->assertTrue($rows->has($c1->id));
        $this->assertTrue($rows->has($c2->id));
        $this->assertSame($expectedLast1->body, $rows[$c1->id]['last_message'] ?? null);
        $this->assertSame($expectedLast2->body, $rows[$c2->id]['last_message'] ?? null);
        $this->assertSame($expectedUnread1, $rows[$c1->id]['unread_count']);
        $this->assertSame('siswa_q1', $rows[$c1->id]['user_name']);
        $this->assertSame(2, $resp->json('waiting_count'));
    }

    public function test_jumlah_query_queue_tidak_naik_seiring_jumlah_percakapan_menunggu(): void
    {
        $admin = $this->makeUser('admin', 'admin_queue_scale');

        $this->makeWaitingConversation('siswa_scale_0');

        $this->actingAs($admin)->getJson('/chatbot/admin/queue')->assertOk(); // pemanasan

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->getJson('/chatbot/admin/queue')->assertOk();
        $baseline = count(DB::getQueryLog());
        DB::disableQueryLog();

        for ($i = 1; $i < 15; $i++) {
            $this->makeWaitingConversation('siswa_scale_' . $i);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $resp = $this->actingAs($admin)->getJson('/chatbot/admin/queue')->assertOk();
        $afterFifteen = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(15, $resp->json('conversations'));
        $this->assertSame(
            $baseline,
            $afterFifteen,
            "Query queue() harus TETAP walau percakapan menunggu bertambah dr 1 ke 15 (skrg {$baseline} vs {$afterFifteen}) — indikasi N+1 pesan-terakhir/unread-count/displayName per percakapan kembali muncul."
        );
    }
}
