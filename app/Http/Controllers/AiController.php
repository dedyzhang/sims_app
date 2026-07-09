<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/*
| Gerbang AI SIMS (FASE 1 — Inti AI Gateway). Endpoint /ai/generate adalah
| gateway generik (mis. smoke-test), dibatasi superadmin. Fitur spesifik
| (chatbot, asisten guru, narasi data) memakai ulang trait InteractsWithAi.
*/
class AiController extends Controller
{
    use InteractsWithAi;

    public function __construct(private GeminiService $gemini) {}

    /** POST /ai/generate — gateway generik. */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:'.config('ai.max_input_chars')],
            'system' => ['nullable', 'string', 'max:2000'],
        ]);

        $userId = $request->user()?->uuid;

        if ($limited = $this->aiRateLimited('generate', $userId)) {
            return $limited;
        }

        try {
            $result = $this->gemini->generate($data['prompt'], ['system' => $data['system'] ?? '']);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, 'generate', config('ai.model'), 0, 0, 'error');

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }

        $this->logAiUsage(
            $userId,
            'generate',
            $result['model'],
            $result['prompt_tokens'],
            $result['completion_tokens'],
            'success',
        );

        return response()->json(['ok' => true, 'answer' => $result['text']]);
    }
}
