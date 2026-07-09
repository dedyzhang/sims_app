<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Services\GeminiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/*
| Asisten Guru (FASE 3). Panel berisi 3 tool untuk mempercepat pekerjaan guru:
| Generator Soal, Perangkum Materi, dan Draft Feedback. Semua memanggil Gemini
| lewat GeminiService; rate limit + audit via trait InteractsWithAi. Digate
| role:guru,walikelas di route.
*/
class AiTeacherController extends Controller
{
    use InteractsWithAi;

    public function __construct(private GeminiService $gemini) {}

    /** GET /ai/teacher — halaman panel Asisten Guru. */
    public function index(): View
    {
        return view('ai.teacher');
    }

    /** POST /ai/teacher/quiz — generator soal/kuis. */
    public function quiz(Request $request): JsonResponse
    {
        $data = $request->validate([
            'topik'   => ['required', 'string', 'max:500'],
            'jumlah'  => ['required', 'integer', 'min:1', 'max:20'],
            'jenis'   => ['required', 'in:pg,esai,campuran'],
            'tingkat' => ['required', 'in:mudah,sedang,sulit'],
            'jenjang' => ['nullable', 'string', 'max:100'],
        ]);

        $jenis = [
            'pg'       => 'pilihan ganda (opsi A–D)',
            'esai'     => 'esai/uraian',
            'campuran' => 'campuran pilihan ganda dan esai',
        ][$data['jenis']];

        $jenjang = $data['jenjang'] ? "untuk jenjang {$data['jenjang']}" : '';

        $prompt = "Buat {$data['jumlah']} soal {$jenis} dengan tingkat kesulitan "
            ."{$data['tingkat']} tentang topik: \"{$data['topik']}\" {$jenjang}. "
            .'Sertakan kunci jawaban.';

        return $this->respond($request, 'teacher_quiz', config('ai.teacher.quiz'), $prompt);
    }

    /** POST /ai/teacher/summary — perangkum materi. */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'materi' => ['required', 'string', 'max:'.config('ai.max_input_chars')],
        ]);

        $prompt = "Rangkum materi berikut menjadi poin-poin ringkas untuk siswa:\n\n".$data['materi'];

        return $this->respond($request, 'teacher_summary', config('ai.teacher.summary'), $prompt);
    }

    /** POST /ai/teacher/feedback — draft komentar/feedback siswa. */
    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'konteks' => ['required', 'string', 'max:'.config('ai.max_input_chars')],
            'nama'    => ['nullable', 'string', 'max:100'],
        ]);

        $nama = $data['nama'] ? "untuk siswa bernama {$data['nama']}" : '';
        $prompt = "Susun draf umpan balik {$nama} berdasarkan konteks berikut:\n\n".$data['konteks'];

        return $this->respond($request, 'teacher_feedback', config('ai.teacher.feedback'), $prompt);
    }

    /** Pipeline bersama: rate limit → Gemini → audit → JSON. */
    private function respond(Request $request, string $feature, string $system, string $prompt): JsonResponse
    {
        $userId = $request->user()->uuid;

        if ($limited = $this->aiRateLimited($feature, $userId)) {
            return $limited;
        }

        try {
            $result = $this->gemini->generate($prompt, [
                'system'            => $system,
                'max_output_tokens' => 2048, // keluaran guru cenderung lebih panjang
            ]);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, $feature, config('ai.model'), 0, 0, 'error');

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 502);
        }

        $this->logAiUsage(
            $userId,
            $feature,
            $result['model'],
            $result['prompt_tokens'],
            $result['completion_tokens'],
            'success',
        );

        return response()->json(['ok' => true, 'answer' => $result['text']]);
    }
}
