<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Models\AiConversation;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/*
| Chatbot Tanya-Jawab AI (FASE 2). Widget mengambang memanggil endpoint ini.
| Percakapan & pesan disimpan per user (di-scope user_uuid) supaya bisa
| dilanjutkan. System prompt diberi konteks FAQ sekolah (config ai.chat.faq).
*/
class AiChatController extends Controller
{
    use InteractsWithAi;

    public function __construct(private GeminiService $gemini) {}

    /** POST /ai/chat — kirim pesan; buat/lanjutkan percakapan. */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message'         => ['required', 'string', 'max:'.config('ai.max_input_chars')],
            'conversation_id' => ['nullable', 'string', 'size:36'],
        ]);

        $userId  = $request->user()->uuid;
        $message = trim($data['message']);

        if ($limited = $this->aiRateLimited('chat', $userId)) {
            return $limited;
        }

        // Ambil percakapan milik user, atau buat baru dengan judul dari pesan pertama.
        $conversation = null;
        if (!empty($data['conversation_id'])) {
            $conversation = AiConversation::where('uuid', $data['conversation_id'])
                ->where('user_uuid', $userId)
                ->first();
        }
        $conversation ??= AiConversation::create([
            'user_uuid' => $userId,
            'title'     => Str::limit($message, 60, '…'),
        ]);

        // Riwayat konteks (pesan-pesan sebelum yang baru ini), urut kronologis.
        $history = $conversation->messages()
            ->latest('created_at')
            ->take(config('ai.chat.history_limit'))
            ->get()
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'text' => $m->content])
            ->values()
            ->all();

        // Simpan pesan pengguna lebih dulu (tetap tersimpan walau AI gagal).
        $conversation->messages()->create([
            'role'           => 'user',
            'content'        => $message,
            'token_estimate' => (int) ceil(mb_strlen($message) / 4),
        ]);

        try {
            $result = $this->generateChat($message, config('ai.chat.faq'), $history);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, 'chat', config('ai.model'), 0, 0, 'error');

            return response()->json([
                'ok'              => false,
                'conversation_id' => $conversation->uuid,
                'message'         => $e->getMessage(),
            ], 502);
        }

        // Tempelkan daftar sumber (bila jawaban di-grounding ke Google Search).
        $answer = $this->appendSources($result['text'], $result['sources'] ?? []);

        $conversation->messages()->create([
            'role'           => 'assistant',
            'content'        => $answer,
            'token_estimate' => $result['completion_tokens'],
        ]);
        $conversation->touch(); // dorong ke atas daftar riwayat

        $this->logAiUsage(
            $userId,
            'chat',
            $result['model'],
            $result['prompt_tokens'],
            $result['completion_tokens'],
            'success',
        );

        return response()->json([
            'ok'              => true,
            'conversation_id' => $conversation->uuid,
            'title'           => $conversation->title,
            'answer'          => $answer,
        ]);
    }

    /**
     * Panggil Gemini untuk chat dengan grounding Google Search bila diaktifkan.
     * Free-tier safe: bila panggilan ber-grounding gagal (mis. grounding tidak
     * tersedia / kuota harian free tier habis), otomatis diulang TANPA grounding
     * agar pengguna tetap dapat jawaban tanpa perlu paket berbayar.
     */
    private function generateChat(string $message, string $system, array $history): array
    {
        $base = ['system' => $system, 'history' => $history];

        if (! config('ai.grounding') || ! $this->shouldGround($message)) {
            return $this->gemini->generate($message, $base);
        }

        try {
            return $this->gemini->generate($message, $base + ['grounding' => true]);
        } catch (RuntimeException $e) {
            report($e); // catat penyebabnya, lalu jatuh ke jawaban tanpa grounding
            return $this->gemini->generate($message, $base);
        }
    }

    /**
     * Heuristik hemat kuota: grounding hanya untuk pesan yang mengandung sinyal
     * butuh info terkini/faktual dari web (lihat config ai.grounding_triggers).
     * Bila daftar trigger kosong → selalu true (grounding untuk semua pesan).
     */
    private function shouldGround(string $message): bool
    {
        $triggers = (array) config('ai.grounding_triggers', []);
        if ($triggers === []) {
            return true;
        }

        $haystack = mb_strtolower($message);
        foreach ($triggers as $trigger) {
            $trigger = mb_strtolower(trim((string) $trigger));
            if ($trigger !== '' && str_contains($haystack, $trigger)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tambahkan blok "Sumber" berisi tautan hasil Google Search (grounding)
     * ke akhir jawaban. Aman bila kosong (mengembalikan teks apa adanya).
     *
     * @param  array<int,array{title:string,uri:string}>  $sources
     */
    private function appendSources(string $text, array $sources): string
    {
        if ($sources === []) {
            return $text;
        }

        $lines = ["\n\nSumber:"];
        foreach ($sources as $i => $s) {
            $lines[] = ($i + 1).'. '.$s['title'].' — '.$s['uri'];
        }

        return $text.implode("\n", $lines);
    }

    /** GET /ai/chat/history — daftar percakapan user (untuk riwayat). */
    public function history(Request $request): JsonResponse
    {
        $items = AiConversation::where('user_uuid', $request->user()->uuid)
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get(['uuid', 'title', 'updated_at'])
            ->map(fn ($c) => [
                'id'    => $c->uuid,
                'title' => $c->title ?: 'Percakapan',
                'time'  => optional($c->updated_at)->diffForHumans(),
            ]);

        return response()->json(['ok' => true, 'conversations' => $items]);
    }

    /** GET /ai/chat/{conversation} — muat pesan satu percakapan (untuk dilanjutkan). */
    public function show(Request $request, string $conversation): JsonResponse
    {
        $conv = AiConversation::where('uuid', $conversation)
            ->where('user_uuid', $request->user()->uuid)
            ->firstOrFail();

        $messages = $conv->messages()
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content]);

        return response()->json([
            'ok'       => true,
            'id'       => $conv->uuid,
            'title'    => $conv->title,
            'messages' => $messages,
        ]);
    }

    /** DELETE /ai/chat/{conversation} — hapus percakapan (beserta pesannya). */
    public function destroy(Request $request, string $conversation): JsonResponse
    {
        AiConversation::where('uuid', $conversation)
            ->where('user_uuid', $request->user()->uuid)
            ->firstOrFail()
            ->delete();

        return response()->json(['ok' => true]);
    }
}
