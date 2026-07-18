<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Jobs\IngestAiDocumentJob;
use App\Models\AiDocument;
use App\Services\GeminiService;
use App\Services\RagService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/*
| RAG Dokumen Sekolah (FASE 5). Admin mengunggah dokumen (PDF/TXT) → RagService
| ekstrak+chunk+embed. Pengguna bertanya → pencarian semantik (cosine) → Gemini
| menjawab HANYA dari kutipan yang ditemukan + sebut sumbernya. AI tak query DB.
*/
class AiRagController extends Controller
{
    use InteractsWithAi;

    public function __construct(
        private GeminiService $gemini,
        private RagService $rag,
    ) {}

    /** GET /ai/rag — daftar dokumen + kotak tanya. */
    public function index(): View
    {
        return view('ai.rag', [
            'documents' => AiDocument::withCount('chunks')->latest()->get(),
            'schoolAiConfigured' => $this->gemini->enabled() && filled(config('ai.api_key')),
            'maxUploadKb' => (int) config('ai.rag.max_upload_kb', 5120),
        ]);
    }

    /** POST /ai/rag — unggah & antrekan proses dokumen. */
    public function store(Request $request): JsonResponse
    {
        if (! filled(config('ai.api_key'))) {
            return response()->json([
                'ok' => false,
                'message' => 'Dokumen AI memakai kunci sekolah (GEMINI_API_KEY di server), bukan API key pribadi Asisten Guru. Minta admin mengisi kunci di .env.',
            ], 422);
        }

        $maxKb = max(100, (int) config('ai.rag.max_upload_kb', 5120));

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'file' => ['required', 'file', 'mimes:pdf,txt', 'max:'.$maxKb],
        ]);

        $file = $request->file('file');
        $title = $data['title'] ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $path = $file->store('ai_documents', 'local');

        $doc = AiDocument::create([
            'user_uuid' => $request->user()->uuid,
            'title' => $title,
            'file_path' => $path,
            'status' => AiDocument::STATUS_PENDING,
        ]);

        $mime = (string) $file->getMimeType();

        if (config('ai.rag.queue_ingest', true)) {
            IngestAiDocumentJob::dispatch($doc->uuid, $mime);
            $doc->refresh();

            return response()->json([
                'ok' => true,
                'message' => "Dokumen \"{$doc->title}\" diantrekan untuk diproses. Muat ulang sebentar lagi bila status masih Pending.",
                'queued' => true,
                'document' => [
                    'id' => $doc->uuid,
                    'title' => $doc->title,
                    'status' => $doc->status,
                    'chunk_count' => $doc->chunk_count,
                ],
            ]);
        }

        $this->rag->ingest($doc, Storage::disk('local')->path($path), $mime);
        $doc->refresh();

        if ($doc->status === AiDocument::STATUS_FAILED) {
            return response()->json([
                'ok' => false,
                'message' => 'Dokumen gagal diproses: '.$doc->error,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => "Dokumen \"{$doc->title}\" diproses ({$doc->chunk_count} potongan).",
            'queued' => false,
            'document' => [
                'id' => $doc->uuid,
                'title' => $doc->title,
                'status' => $doc->status,
                'chunk_count' => $doc->chunk_count,
            ],
        ]);
    }

    /** DELETE /ai/rag/{document} — hapus dokumen, chunk, dan file. */
    public function destroy(Request $request, string $document): JsonResponse
    {
        $doc = AiDocument::findOrFail($document);
        abort_unless($doc->user_uuid === $request->user()->uuid || $request->user()->isSuperAdmin(), 403);

        if ($doc->file_path) {
            Storage::disk('local')->delete($doc->file_path);
        }
        $doc->delete(); // chunks cascade

        return response()->json(['ok' => true]);
    }

    /** POST /ai/rag/ask — tanya-jawab berbasis dokumen (dengan sitasi). */
    public function ask(Request $request): JsonResponse
    {
        if (! filled(config('ai.api_key'))) {
            return response()->json([
                'ok' => false,
                'message' => 'Dokumen AI memakai kunci sekolah (GEMINI_API_KEY di server). Minta admin mengisi kunci di .env.',
            ], 422);
        }

        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'document_id' => ['nullable', 'string', 'exists:ai_documents,uuid'],
        ]);

        $userId = $request->user()->uuid;

        if ($limited = $this->aiRateLimited('rag', $userId)) {
            return $limited;
        }

        try {
            $hits = $this->rag->search($data['question'], null, $data['document_id'] ?? null);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, 'rag', config('ai.model'), 0, 0, 'error');

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }

        if ($hits === []) {
            return response()->json([
                'ok' => true,
                'answer' => 'Belum ada dokumen siap yang bisa dijadikan rujukan. Unggah dokumen dan tunggu status Siap, atau coba pertanyaan lain.',
                'sources' => [],
            ]);
        }

        $context = '';
        foreach ($hits as $h) {
            $context .= "[Dokumen: {$h['title']}]\n{$h['content']}\n\n";
        }
        $prompt = "KONTEKS:\n{$context}\nPERTANYAAN: {$data['question']}";

        try {
            $result = $this->gemini->generate($prompt, [
                'system' => config('ai.rag.system'),
                'temperature' => 0.3,
            ]);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, 'rag', config('ai.model'), 0, 0, 'error');

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }

        $this->logAiUsage($userId, 'rag', $result['model'], $result['prompt_tokens'], $result['completion_tokens'], 'success');

        $sources = [];
        foreach ($hits as $h) {
            $t = $h['title'];
            if (! isset($sources[$t]) || $h['score'] > $sources[$t]) {
                $sources[$t] = round($h['score'], 3);
            }
        }
        arsort($sources);

        return response()->json([
            'ok' => true,
            'answer' => $result['text'],
            'sources' => collect($sources)->map(fn ($score, $title) => ['title' => $title, 'score' => $score])->values(),
        ]);
    }
}
