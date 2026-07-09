<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
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
        ]);
    }

    /** POST /ai/rag — unggah & proses dokumen (sinkron). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'file'  => ['required', 'file', 'mimes:pdf,txt', 'max:10240'], // 10 MB
        ]);

        $file  = $request->file('file');
        $title = $data['title'] ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $path = $file->store('ai_documents', 'local');

        $doc = AiDocument::create([
            'user_uuid' => $request->user()->uuid,
            'title'     => $title,
            'file_path' => $path,
            'status'    => AiDocument::STATUS_PENDING,
        ]);

        $this->rag->ingest($doc, Storage::disk('local')->path($path), $file->getMimeType());
        $doc->refresh();

        if ($doc->status === AiDocument::STATUS_FAILED) {
            return response()->json([
                'ok'      => false,
                'message' => 'Dokumen gagal diproses: '.$doc->error,
            ], 422);
        }

        return response()->json([
            'ok'       => true,
            'message'  => "Dokumen \"{$doc->title}\" diproses ({$doc->chunk_count} potongan).",
            'document' => [
                'id'          => $doc->uuid,
                'title'       => $doc->title,
                'status'      => $doc->status,
                'chunk_count' => $doc->chunk_count,
            ],
        ]);
    }

    /** DELETE /ai/rag/{document} — hapus dokumen, chunk, dan file. */
    public function destroy(string $document): JsonResponse
    {
        $doc = AiDocument::findOrFail($document);

        if ($doc->file_path) {
            Storage::disk('local')->delete($doc->file_path);
        }
        $doc->delete(); // chunks cascade

        return response()->json(['ok' => true]);
    }

    /** POST /ai/rag/ask — tanya-jawab berbasis dokumen (dengan sitasi). */
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        $userId = $request->user()->uuid;

        if ($limited = $this->aiRateLimited('rag', $userId)) {
            return $limited;
        }

        try {
            $hits = $this->rag->search($data['question']);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, 'rag', config('ai.model'), 0, 0, 'error');

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }

        if ($hits === []) {
            return response()->json([
                'ok'      => true,
                'answer'  => 'Belum ada dokumen yang bisa dijadikan rujukan. Unggah dokumen terlebih dahulu.',
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
                'system'      => config('ai.rag.system'),
                'temperature' => 0.3,
            ]);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, 'rag', config('ai.model'), 0, 0, 'error');

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }

        $this->logAiUsage($userId, 'rag', $result['model'], $result['prompt_tokens'], $result['completion_tokens'], 'success');

        // Sumber unik (judul dokumen dengan skor tertinggi).
        $sources = [];
        foreach ($hits as $h) {
            $t = $h['title'];
            if (!isset($sources[$t]) || $h['score'] > $sources[$t]) {
                $sources[$t] = round($h['score'], 3);
            }
        }
        arsort($sources);

        return response()->json([
            'ok'      => true,
            'answer'  => $result['text'],
            'sources' => collect($sources)->map(fn ($score, $title) => ['title' => $title, 'score' => $score])->values(),
        ]);
    }
}
