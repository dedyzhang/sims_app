<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Models\AiTeacherHistory;
use App\Models\Classroom;
use App\Models\Setting;
use App\Models\TeacherPresentation;
use App\Services\GameQuizImporter;
use App\Services\GeminiService;
use App\Services\TeacherMaterialException;
use App\Services\TeacherMaterialService;
use App\Support\DocumentText;
use App\Support\LearningDocument;
use App\Support\LearningDocxBuilder;
use App\Support\ModulAktif;
use App\Support\PresentationSlides;
use App\Support\QuizDocument;
use App\Support\QuizDocxBuilder;
use App\Support\QuizImageEnricher;
use App\Support\SchoolLetterhead;
use App\Support\TeacherOutputLanguage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/*
| Asisten Guru (FASE 3). Panel berisi 3 tool untuk mempercepat pekerjaan guru:
| Generator Soal, Perangkum Materi, dan Catatan Siswa. Semua memanggil Gemini
| lewat GeminiService; rate limit + audit via trait InteractsWithAi. Digate
| role:guru,walikelas di route.
*/
class AiTeacherController extends Controller
{
    use InteractsWithAi;

    private const QUIZ_TYPES = [
        'pg_kompleks' => 'Pilihan Ganda Kompleks',
        'pg' => 'Pilihan Ganda',
        'benar_salah' => 'Benar/Salah',
        'mencocokkan' => 'Mencocokkan',
        'isian' => 'Isian',
    ];

    private const LEGACY_QUIZ_TYPES = [
        'pg' => ['pg'],
        'esai' => ['isian'],
        'campuran' => ['pg_kompleks', 'pg', 'benar_salah', 'mencocokkan', 'isian'],
    ];

    public function __construct(
        private GeminiService $gemini,
        private TeacherMaterialService $materials,
    ) {}

    /** GET /ai/teacher - halaman panel Asisten Guru. */
    public function index(): View
    {
        $histories = AiTeacherHistory::query()
            ->where('user_uuid', auth()->id())
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AiTeacherHistory $history) => $this->historyPayload($history))
            ->values();

        $user = auth()->user();

        $arenaClassrooms = collect();
        if (ModulAktif::aktif('arena_belajar')) {
            $arenaClassrooms = Classroom::query()
                ->where('status', 'published')
                ->latest()
                ->limit(80)
                ->get()
                ->filter(fn (Classroom $c) => $user->can('manage', $c))
                ->values()
                ->map(fn (Classroom $c) => [
                    'uuid' => $c->uuid,
                    'title' => $c->title,
                ]);
        }

        $hasApiKey = $user->hasGeminiApiKey();
        $canvaStatus = app(\App\Services\CanvaConnectService::class)->statusPayload($user);

        return view('ai.teacher', [
            'histories' => $histories,
            'quotaUsage' => $this->aiPublicQuotaUsage(true, $user->uuid),
            'canViewQuotaUsage' => false,
            'arenaClassrooms' => $arenaClassrooms,
            'arenaBelajarAktif' => ModulAktif::aktif('arena_belajar'),
            'launcherAktif' => (Setting::get('tp_launcher_aktif', '1') ?? '1') === '1',
            'needsApiKeySetup' => ! $hasApiKey,
            'canvaStatus' => $canvaStatus,
            'teacherMaterials' => $this->materials->listPayloads($user->uuid),
            'externalAccounts' => [
                'has_gemini_api_key' => $hasApiKey,
                'gemini_api_key_masked' => $user->geminiApiKeyMasked(),
                'canva_belajar_id' => $user->canva_belajar_id,
            ],
        ]);
    }

    /**
     * GET /ai/teacher/materials — daftar buku/materi unggahan guru (Generator Soal RAG).
     * Dipakai UI untuk pilih ulang tanpa upload, dan polling status pending/partial.
     */
    public function materials(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'materials' => $this->materials->listPayloads($request->user()->uuid),
        ]);
    }

    /**
     * POST /ai/teacher/external-prompt — susun prompt berformat untuk ditempel di Gemini web.
     * Tidak memanggil GeminiService; generate memakai akun Google guru di gemini.google.com.
     */
    public function externalPrompt(Request $request): JsonResponse
    {
        $tool = $request->validate([
            'tool' => ['required', 'in:quiz,learning,summary,feedback,chat'],
        ])['tool'];

        $built = match ($tool) {
            'quiz' => $this->composeQuiz($request),
            'learning' => $this->composeLearningForExternal($request),
            'summary' => $this->composeSummary($request),
            'feedback' => $this->composeFeedback($request),
            'chat' => $this->composeChat($request),
        };

        if ($built instanceof JsonResponse) {
            return $built;
        }

        // Cadangan Gemini web tidak bisa unggah foto otomatis dari SIMS.
        if (! empty($built['vision_images'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Foto buku hanya digenerate di SIMS lewat API AI Studio (tombol Buat Soal / Buat RPM). Cadangan Gemini web tidak mendukung lampiran foto otomatis.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'prompt' => $this->buildExternalPastePrompt($built),
            'gemini_url' => 'https://gemini.google.com/app',
            'tool' => $tool,
            'title' => (string) ($built['title'] ?? $built['history']['title'] ?? 'Asisten Guru'),
        ]);
    }

    /**
     * POST /ai/teacher/external-result — terima jawaban yang ditempel dari Gemini web.
     */
    public function externalResult(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tool' => ['required', 'in:quiz,learning,summary,feedback,chat'],
            'title' => ['nullable', 'string', 'max:180'],
            'answer' => ['required', 'string', 'min:1', 'max:100000'],
        ]);

        $meta = match ($data['tool']) {
            'quiz' => ['type' => 'quiz', 'type_label' => 'Generator Soal'],
            'learning' => ['type' => 'rpp', 'type_label' => 'RPM Learning'],
            'summary' => ['type' => 'summary', 'type_label' => 'Perangkum Materi'],
            'feedback' => ['type' => 'feedback', 'type_label' => 'Catatan Siswa'],
            'chat' => ['type' => 'gemini_chat', 'type_label' => 'Nalar Guru'],
        };

        $title = trim((string) ($data['title'] ?? '')) ?: $meta['type_label'];
        $answer = SchoolLetterhead::ensurePrefix(trim($data['answer']));

        $history = $this->storeHistory($request->user()->uuid, [
            'type' => $meta['type'],
            'type_label' => $meta['type_label'],
            'title' => $title,
            'metadata' => [
                'via' => 'gemini_web',
            ],
        ], $answer);

        return response()->json([
            'ok' => true,
            'answer' => $answer,
            'history' => $history,
        ]);
    }

    /** POST /ai/teacher/chat — cadangan API sekolah (UI utama memakai external-prompt). */
    public function chat(Request $request): JsonResponse
    {
        $built = $this->composeChat($request);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        $options = [
            'thinking_level' => 'low',
        ];
        if (! empty($built['history_turns'])) {
            $options['history'] = $built['history_turns'];
        }
        if (! empty($built['answer_style'])) {
            $options['answer_style'] = $built['answer_style'];
        }
        if (! empty($built['long_timeout'])) {
            $options['timeout'] = (int) config('ai.long_timeout');
        }

        return $this->respond(
            $request,
            'teacher_chat',
            $built['system'],
            $built['prompt'],
            (int) ($built['max_output_tokens'] ?? 4096),
            $options,
            $built['history'],
        );
    }

    /**
     * @return array{system:string,prompt:string,answer_style?:string,title:string,history:array,history_turns?:list<array{role:string,text:string}>,max_output_tokens?:int,long_timeout?:bool}|JsonResponse
     */
    private function composeChat(Request $request): array|JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.config('ai.max_input_chars')],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.text' => ['required_with:history', 'string', 'max:8000'],
        ]);

        $historyTurns = collect($data['history'] ?? [])
            ->map(fn (array $turn) => [
                'role' => $turn['role'],
                'text' => $turn['text'],
            ])
            ->take(-12)
            ->values()
            ->all();

        $message = trim($data['message']);

        if ($this->messageLooksLikeQuizRequest($message)) {
            $params = $this->inferQuizParamsFromMessage($message);
            $formatInstruction = $this->quizFormatInstruction(
                $params['jumlah'],
                $params['jenis_soal'],
                $params['tingkat'],
                $params['jenjang'],
                $params['topik'],
            );
            $jenis = $this->quizTypeSummary($params['jenis_soal']);
            $jenjangLine = $params['jenjang'] ? " untuk jenjang {$params['jenjang']}" : '';

            $prompt = "Buat {$params['jumlah']} soal ({$jenis}) dengan tingkat kesulitan "
                ."{$params['tingkat']}{$jenjangLine} tentang topik: \"{$params['topik']}\".\n\n"
                ."PERMINTAAN ASLI GURU:\n{$message}\n\n"
                .$formatInstruction;

            return [
                'system' => (string) config('ai.teacher.quiz'),
                'prompt' => $prompt,
                'answer_style' => 'Tulis sebagai dokumen soal teks polos siap cetak sesuai format yang diminta. JANGAN memakai Markdown, heading #, atau bullet dekoratif.',
                'title' => Str::limit($params['topik'] !== '' ? $params['topik'] : $message, 90),
                'history_turns' => $historyTurns,
                'max_output_tokens' => $this->quizMaxOutputTokens($params['jumlah'], $params['tingkat']),
                'long_timeout' => true,
                'history' => [
                    'type' => 'gemini_chat',
                    'type_label' => 'Nalar Guru',
                    'title' => Str::limit($params['topik'] !== '' ? $params['topik'] : $message, 90),
                    'metadata' => [
                        'prompt' => Str::limit($message, 2000, ''),
                        'turns' => count($historyTurns) + 1,
                        'mode' => 'quiz_format',
                        'jumlah' => $params['jumlah'],
                        'jenis_soal' => $params['jenis_soal'],
                        'tingkat' => $params['tingkat'],
                        'jenjang' => $params['jenjang'],
                    ],
                ],
            ];
        }

        $quizFormatReminder = "\n\nPENTING: Jika pengguna meminta soal, kuis, atau soal evaluasi, "
            .'jawab HANYA dengan dokumen soal teks polos mengikuti format Generator Soal '
            .'(kop sekolah, SOAL EVALUASI, identitas, Petunjuk Pengerjaan, Bagian soal, '
            .'Kunci Jawaban & Pedoman Penilaian). Jangan pakai Markdown.';

        $nalarAnswerStyle = SchoolLetterhead::asPromptBlock()."\n\n"
            .'Setiap jawaban Nalar Guru WAJIB teks polos berstruktur, siap disalin '
            .'ke Word/WhatsApp/Google Docs. Mulai SELALU dengan kop surat di atas. '
            .'Langsung ke isi tanpa pembuka/penutup basa-basi. '
            .'Judul bagian pakai HURUF KAPITAL di baris sendiri + baris kosong. '
            .'Poin pakai "- " atau nomor "1. 2. 3." (satu per baris). '
            .'Paragraf pendek, satu baris kosong antar bagian. '
            .'JANGAN memakai Markdown (#, **, *, ```, tabel pipe) atau emoji berlebih.';

        return [
            'system' => (string) config('ai.teacher.chat').$quizFormatReminder,
            'prompt' => $message,
            'answer_style' => $nalarAnswerStyle,
            'title' => Str::limit($message, 90),
            'history_turns' => $historyTurns,
            'history' => [
                'type' => 'gemini_chat',
                'type_label' => 'Nalar Guru',
                'title' => Str::limit($message, 90),
                'metadata' => [
                    'prompt' => Str::limit($message, 2000, ''),
                    'turns' => count($historyTurns) + 1,
                    'mode' => 'chat',
                ],
            ],
        ];
    }

    /** Compose learning tanpa bentrok field `tool` eksternal (quiz|learning|…). */
    private function composeLearningForExternal(Request $request): array|JsonResponse
    {
        $learningTool = $request->input('learning_tool', $request->input('learning.tool', 'rpp'));
        $dup = $request->duplicate();
        $dup->merge(['tool' => $learningTool]);

        return $this->composeLearning($dup);
    }

    /** Gabungkan system + gaya + permintaan jadi satu teks siap tempel di Gemini web. */
    private function buildExternalPastePrompt(array $built): string
    {
        $parts = [];
        $system = trim((string) ($built['system'] ?? ''));
        $answerStyle = trim((string) ($built['answer_style'] ?? ''));
        $prompt = trim((string) ($built['prompt'] ?? ''));

        if ($system !== '') {
            $parts[] = "PERAN / INSTRUKSI SISTEM:\n{$system}";
        }
        if ($answerStyle !== '') {
            $parts[] = "GAYA JAWABAN:\n{$answerStyle}";
        }
        if (! empty($built['history_turns']) && is_array($built['history_turns'])) {
            $lines = [];
            foreach ($built['history_turns'] as $turn) {
                $role = (($turn['role'] ?? '') === 'assistant') ? 'Asisten' : 'Guru';
                $text = trim((string) ($turn['text'] ?? ''));
                if ($text !== '') {
                    $lines[] = "{$role}: {$text}";
                }
            }
            if ($lines !== []) {
                $parts[] = "RIWAYAT PERCAKAPAN:\n".implode("\n\n", $lines);
            }
        }
        $parts[] = "PERMINTAAN:\n{$prompt}";
        $parts[] = 'Jawab langsung sesuai instruksi di atas. Jangan menambah pembuka atau penjelasan di luar format yang diminta.';

        return implode("\n\n---\n\n", $parts);
    }

    /** Deteksi permintaan pembuatan soal/kuis di chat Gemini. */
    private function messageLooksLikeQuizRequest(string $message): bool
    {
        return (bool) preg_match(
            '/\b(soal|kuis|quiz|evaluasi|ulangan|pilihan\s*ganda|benar\s*\/?\s*salah|isian|mencocokkan|pg\b)/iu',
            $message,
        );
    }

    /**
     * Infer parameter soal dari teks chat (fallback ke default Generator Soal).
     *
     * @return array{jumlah:int,jenis_soal:list<string>,tingkat:string,jenjang:?string,topik:string}
     */
    private function inferQuizParamsFromMessage(string $message): array
    {
        $jumlah = 5;
        if (preg_match('/\b(\d{1,2})\s*(soal|butir|nomor|item)\b/iu', $message, $m)
            || preg_match('/\b(buat|bikin|generate)\s+(\d{1,2})\b/iu', $message, $m2)) {
            $n = (int) ($m[1] ?? $m2[2] ?? 5);
            $jumlah = max(1, min(20, $n));
        }

        $tingkat = 'sedang';
        if (preg_match('/\b(mudah|sedang|sulit|sukar)\b/iu', $message, $tm)) {
            $tingkat = strtolower($tm[1]) === 'sukar' ? 'sulit' : strtolower($tm[1]);
        }

        $jenjang = null;
        if (preg_match('/\b(kelas\s*[0-9IVX]+(?:\s*[A-Z])?|\bSD\b|\bSMP\b|\bSMA\b|\bSMK\b)(?:\s*[\/,]?\s*semester\s*\d+)?/iu', $message, $jm)) {
            $jenjang = trim($jm[0]);
        }

        $jenisSoal = [];
        $map = [
            'pg_kompleks' => '/pilihan\s*ganda\s*kompleks|pg\s*kompleks|multi\s*kunci/iu',
            'pg' => '/pilihan\s*ganda(?!\s*kompleks)|\bpg\b(?!\s*kompleks)/iu',
            'benar_salah' => '/benar\s*\/?\s*salah|true\s*\/?\s*false|\bB\/S\b/iu',
            'mencocokkan' => '/mencocokkan|menjodohkan|matching/iu',
            'isian' => '/\bisian\b|essay|esai|uraian/iu',
        ];
        foreach ($map as $type => $pattern) {
            if (preg_match($pattern, $message)) {
                $jenisSoal[] = $type;
            }
        }
        if ($jenisSoal === []) {
            // Default sama dengan form Generator Soal (PG).
            $jenisSoal = ['pg'];
        }

        // Topik: buang frasa perintah umum, sisakan inti.
        $topik = preg_replace(
            '/\b(buatkan|buat|bikin|generate|tolong|mohon|segera)\b/iu',
            ' ',
            $message,
        );
        $topik = preg_replace(
            '/\b(\d{1,2}\s*)?(soal|kuis|quiz|evaluasi|ulangan|butir)\b/iu',
            ' ',
            (string) $topik,
        );
        $topik = preg_replace(
            '/\b(pilihan\s*ganda\s*kompleks|pilihan\s*ganda|benar\s*\/?\s*salah|mencocokkan|isian|mudah|sedang|sulit|sukar)\b/iu',
            ' ',
            (string) $topik,
        );
        $topik = preg_replace('/\s+/u', ' ', trim((string) $topik));
        if ($topik === '' || mb_strlen($topik) < 3) {
            $topik = Str::limit($message, 120, '');
        }

        return [
            'jumlah' => $jumlah,
            'jenis_soal' => array_values(array_unique($jenisSoal)),
            'tingkat' => $tingkat,
            'jenjang' => $jenjang,
            'topik' => Str::limit($topik, 200, ''),
        ];
    }

    /** POST /ai/teacher/presentasi-from-chat — buat Studio Presentasi dari jawaban Gemini. */
    public function presentasiFromChat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'outline' => ['required', 'string', 'max:50000'],
            'subject' => ['nullable', 'string', 'max:120'],
        ]);

        $slides = PresentationSlides::fromOutline($data['outline']);

        $item = TeacherPresentation::create([
            'user_uuid' => $request->user()->uuid,
            'title' => $data['title'],
            'subject' => $data['subject'] ?? null,
            'status' => 'draft',
            'outline' => $data['outline'],
            'slides' => $slides !== [] ? $slides : null,
            'last_opened_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Outline dikirim ke Studio Presentasi.',
            'redirect' => route('ai.teacher.presentasi.show', $item),
            'presentation' => [
                'uuid' => $item->uuid,
                'title' => $item->title,
                'subject' => $item->subject,
                'status' => $item->status,
                'updated_at' => optional($item->updated_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * PUT/POST /ai/teacher/gemini-key — simpan API key Gemini pribadi (terenkripsi).
     */
    public function updateGeminiKey(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gemini_api_key' => ['required', 'string', 'min:20', 'max:512'],
        ], [
            'gemini_api_key.required' => 'API key wajib diisi.',
            'gemini_api_key.min' => 'API key terlalu pendek. Salin ulang dari Google AI Studio.',
        ]);

        $plain = trim($data['gemini_api_key']);

        try {
            $this->gemini->probeApiKey($plain);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage() ?: 'API key tidak valid atau belum aktif.',
            ], 422);
        }

        $user = $request->user();
        $user->setGeminiApiKey($plain);
        $user->refresh();

        return response()->json([
            'ok' => true,
            'message' => 'API key disimpan. Generate berjalan di dalam SIMS memakai akun Gemini Anda.',
            'accounts' => [
                'has_gemini_api_key' => true,
                'gemini_api_key_masked' => $user->geminiApiKeyMasked(),
            ],
        ]);
    }

    /** DELETE /ai/teacher/gemini-key — hapus API key pribadi. */
    public function destroyGeminiKey(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->clearGeminiApiKey();

        return response()->json([
            'ok' => true,
            'message' => 'API key dihapus. Generate akan terkunci sampai key baru disimpan.',
            'accounts' => [
                'has_gemini_api_key' => false,
                'gemini_api_key_masked' => null,
            ],
            'needs_api_key' => true,
        ]);
    }

    /** Blok generate bila guru belum menyimpan API key Gemini pribadi. */
    private function requireTeacherGeminiKey(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if ($user->hasGeminiApiKey() && $user->plainGeminiApiKey() !== null) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'Simpan API key Gemini dari Google AI Studio terlebih dahulu untuk generate di SIMS.',
            'needs_api_key' => true,
        ], 422);
    }

    /** API key pribadi wajib untuk generate di dalam SIMS. */
    private function requireTeacherReady(Request $request): ?JsonResponse
    {
        return $this->requireTeacherGeminiKey($request);
    }

    /**
     * POST /ai/teacher/quiz/send-arena — simpan teks soal ke session lalu buka form Arena.
     */
    public function sendToArena(Request $request): RedirectResponse
    {
        abort_unless(ModulAktif::aktif('arena_belajar'), 403, 'Modul Arena Belajar nonaktif.');

        $data = $request->validate([
            'classroom_id' => ['required', 'uuid', 'exists:classrooms,uuid'],
            'raw_text' => ['required', 'string', 'max:50000'],
            'title' => ['nullable', 'string', 'max:200'],
        ]);

        $classroom = Classroom::where('uuid', $data['classroom_id'])->firstOrFail();
        $this->authorize('manage', $classroom);

        if (! GameQuizImporter::looksLikeImportableQuiz($data['raw_text'])) {
            return redirect()
                ->route('ai.teacher.index', ['tab' => 'quiz'])
                ->with('error', 'Teks belum berbentuk soal yang bisa diimpor. Buat soal lewat Nalar/Generator (ada nomor soal + opsi atau SOAL EVALUASI), lalu kirim lagi.');
        }

        session([
            'arena_ai_import' => [
                'raw_text' => $data['raw_text'],
                'title' => $data['title'] ?? null,
            ],
        ]);

        return redirect()
            ->route('classroom.arena.create', $classroom)
            ->with('success', 'Soal dari Asisten Guru siap diimpor. Periksa kunci jawaban lalu simpan.');
    }

    /** GET /ai/teacher/quota — snapshot kuota live (dipoll UI). */
    public function quota(Request $request): JsonResponse
    {
        $fresh = $request->boolean('fresh');
        $userId = $request->user()?->uuid;

        return response()->json([
            'ok' => true,
            'quota' => $this->aiPublicQuotaUsage($fresh, $userId),
        ]);
    }

    /**
     * POST /ai/teacher/ocr — foto buku (kamera HP) → teks via Gemini vision.
     * Foto tidak disimpan permanen; hanya dikirim ke model lalu dibuang.
     */
    public function ocr(Request $request): JsonResponse
    {
        if ($blocked = $this->requireTeacherReady($request)) {
            return $blocked;
        }

        $maxImages = max(1, (int) config('ai.ocr.max_images', 5));
        $maxKb = max(256, (int) ceil(((int) config('ai.ocr.max_bytes', 4 * 1024 * 1024)) / 1024));

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.$maxImages],
            // image = cek isi biner (bukan hanya ekstensi); mimes membatasi format OCR.
            'images.*' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKb],
            'scope' => ['nullable', 'in:quiz,learning'],
            'title' => ['nullable', 'string', 'max:180'],
        ], [
            'images.required' => 'Unggah minimal satu foto halaman buku.',
            'images.max' => "Maksimal {$maxImages} foto per sekali baca.",
            'images.*.image' => 'File harus berupa gambar yang valid.',
            'images.*.mimes' => 'Format foto harus JPEG, PNG, atau WebP.',
            'images.*.max' => 'Setiap foto maksimal '.round($maxKb / 1024, 1).' MB. Kompres di HP lalu coba lagi.',
        ]);

        $user = $request->user();
        $userId = $user->uuid;
        $apiKey = $user->plainGeminiApiKey();

        if ($limited = $this->aiRateLimited('teacher_ocr', $userId)) {
            return $limited;
        }

        $prepared = [];
        foreach ($request->file('images', []) as $file) {
            $binary = $this->prepareOcrImageBinary($file->getRealPath(), $file->getMimeType() ?: 'image/jpeg');
            if ($binary === null) {
                continue;
            }
            $prepared[] = $binary;
        }

        if ($prepared === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Foto tidak bisa diproses. Ambil ulang dengan format JPEG/PNG.',
            ], 422);
        }

        try {
            $result = $this->gemini->visionText($prepared, [
                'api_key' => $apiKey,
                'timeout' => (int) config('ai.ocr.timeout', 60),
                'max_output_tokens' => 4096,
            ]);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, 'teacher_ocr', config('ai.model'), 0, 0, 'error');

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'quota' => $this->aiPublicQuotaUsage(false, $userId),
            ], 502);
        }

        $this->logAiUsage(
            $userId,
            'teacher_ocr',
            $result['model'] ?? config('ai.model'),
            $result['prompt_tokens'] ?? 0,
            $result['completion_tokens'] ?? 0,
            'success',
        );

        $text = trim((string) ($result['text'] ?? ''));
        $upper = mb_strtoupper($text);
        if ($text === '' || str_contains($upper, 'TIDAK_TERBACA')) {
            return response()->json([
                'ok' => false,
                'code' => 'image_unreadable',
                'message' => 'Teks tidak terbaca dari foto (mungkin buram atau gelap). Potret ulang halaman dengan cahaya cukup dan fokus tajam.',
                'quota' => $this->aiPublicQuotaUsage(false, $userId),
            ], 422);
        }

        $maxChars = max(4000, (int) config('ai.max_input_chars', 8000) * 2);
        // Sisakan ruang untuk kop + stempel sumber (anti-plagiarisme).
        $stampBudget = 900;
        $bodyMax = max(2000, $maxChars - $stampBudget);
        if (mb_strlen($text) > $bodyMax) {
            $text = mb_substr($text, 0, $bodyMax);
        }

        $scope = $request->input('scope', 'quiz') === 'learning' ? 'learning' : 'quiz';
        $pageCount = count($prepared);
        $customTitle = trim((string) $request->input('title', ''));
        $title = $customTitle !== ''
            ? $customTitle
            : ('Teks scan buku · '.now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d/m/Y H:i')
                .($pageCount > 1 ? " · {$pageCount} halaman" : ''));

        // Kop sekolah + stempel sumber digital (trademark / pertanggungjawaban).
        $text = SchoolLetterhead::ensureOcrAttribution($text, [
            'pages' => $pageCount,
        ]);

        // Simpan ke History Generate agar bisa dipakai ulang (tanpa menyimpan file foto).
        $history = $this->storeHistory($userId, [
            'type' => 'ocr_scan',
            'type_label' => 'Scan Buku',
            'title' => $title,
            'metadata' => [
                'source' => 'camera_ocr',
                'scope' => $scope,
                'char_count' => mb_strlen($text),
                'pages' => $pageCount,
                'attribution' => 'school_letterhead+scan_stamp',
                'via' => 'sims',
            ],
        ], $text);

        return response()->json([
            'ok' => true,
            'text' => $text,
            'char_count' => mb_strlen($text),
            'history' => $history,
            'quota' => $this->aiPublicQuotaUsage(false, $userId),
        ]);
    }

    /** DELETE /ai/teacher/history/{history} - hapus satu item history milik guru sendiri. */
    public function destroyHistory(AiTeacherHistory $history): JsonResponse
    {
        // History bersifat pribadi: guru lain (atau wali kelas) tak boleh menghapus milik orang.
        abort_unless($history->user_uuid === auth()->id(), 403);

        $history->delete();

        return response()->json(['ok' => true]);
    }

    /** POST /ai/teacher/quiz - generator soal/kuis. */
    public function quiz(Request $request): JsonResponse
    {
        $built = $this->composeQuiz($request);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        // Samakan dengan learning: multi-chunk auto-continue butuh wall-clock lebih longgar.
        @set_time_limit($this->teacherExecutionTimeLimit());

        $jumlah = (int) ($built['history']['metadata']['jumlah'] ?? 10);
        $tingkat = (string) ($built['history']['metadata']['tingkat'] ?? 'sedang');
        $soalBergambar = (bool) ($built['soal_bergambar'] ?? false);

        if ($blocked = $this->requireTeacherReady($request)) {
            return $blocked;
        }

        $user = $request->user();
        $userId = $user->uuid;
        $apiKey = $user->plainGeminiApiKey();

        if ($limited = $this->aiRateLimited('teacher_quiz', $userId)) {
            return $limited;
        }

        $maxOut = $this->quizMaxOutputTokens($jumlah, $tingkat);
        $visionImages = $built['vision_images'] ?? [];

        try {
            // Foto buku: multimodal lewat key AI Studio pribadi guru (bukan OCR dulu).
            if (is_array($visionImages) && $visionImages !== []) {
                $result = $this->gemini->visionText($visionImages, $this->teacherGenerateOptions() + [
                    'api_key' => $apiKey,
                    'prompt' => $built['prompt'],
                    'system' => trim($built['system']."\n\n".$built['answer_style']),
                    'max_output_tokens' => $maxOut,
                    'temperature' => 0.4,
                    'timeout' => (int) config('ai.long_timeout'),
                    'include_global_system_prompt' => $built['include_global_system_prompt'] ?? true,
                ]);
            } else {
                $result = $this->gemini->generate($built['prompt'], $this->teacherGenerateOptions() + [
                    'system' => $built['system'],
                    'max_output_tokens' => $maxOut,
                    'api_key' => $apiKey,
                    'answer_style' => $built['answer_style'],
                    'thinking_level' => 'low',
                    'timeout' => (int) config('ai.long_timeout'),
                    'include_global_system_prompt' => $built['include_global_system_prompt'] ?? true,
                ]);
            }
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, 'teacher_quiz', config('ai.model'), 0, 0, 'error');

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'quota' => $this->aiPublicQuotaUsage(),
            ], 502);
        }

        $answer = SchoolLetterhead::ensurePrefix($result['text']);
        $images = [];
        $imageMeta = null;

        if ($soalBergambar) {
            @set_time_limit((int) config('ai.image.timeout', 90) * ((int) config('ai.image.max_per_quiz', 5) + 1));
            $enriched = app(QuizImageEnricher::class)->enrich($answer, $apiKey, $userId);
            $answer = SchoolLetterhead::ensurePrefix($enriched['text']);
            $images = $enriched['images'];
            $imageMeta = [
                'generated' => $enriched['generated'],
                'failed' => $enriched['failed'],
                'max' => (int) config('ai.image.max_per_quiz', 5),
            ];

            if ($enriched['generated'] > 0) {
                $this->logAiUsage(
                    $userId,
                    'teacher_quiz_image',
                    $images[0]['model'] ?? (string) config('ai.image.model'),
                    0,
                    $enriched['generated'],
                    'success',
                );
            }
        }

        $this->logAiGenerationUsage(
            $userId,
            'teacher_quiz',
            $result,
            'success',
        );

        $historyPayload = $built['history'];
        if ($imageMeta !== null) {
            $historyPayload['metadata']['images'] = $imageMeta;
        }

        $history = $this->storeHistory($userId, $historyPayload, $answer);

        $payload = [
            'ok' => true,
            'answer' => $answer,
            'history' => $history,
            'quota' => $this->aiPublicQuotaUsage(),
        ];

        if (! empty($result['truncated'])) {
            $payload['warning'] = 'Jawaban AI masih terpotong setelah dilanjutkan otomatis. Kurangi cakupan topik, jumlah soal, atau lampiran lalu coba lagi. Teks parsial tetap disimpan.';
        }

        if ($soalBergambar) {
            $payload['images'] = $images;
            $payload['image_meta'] = $imageMeta;
            if (($imageMeta['generated'] ?? 0) === 0 && ($imageMeta['failed'] ?? 0) > 0) {
                $imageWarning = 'Soal teks berhasil, tetapi generate gambar gagal. Penanda [GAMBAR: ...] tetap di dokumen agar bisa dilampirkan manual.';
                $payload['warning'] = isset($payload['warning'])
                    ? $payload['warning'].' '.$imageWarning
                    : $imageWarning;
            } elseif (($imageMeta['generated'] ?? 0) > 0 && ($imageMeta['failed'] ?? 0) > 0) {
                $imageWarning = "Berhasil membuat {$imageMeta['generated']} gambar; {$imageMeta['failed']} gagal.";
                $payload['warning'] = isset($payload['warning'])
                    ? $payload['warning'].' '.$imageWarning
                    : $imageWarning;
            }
        }

        return response()->json($payload);
    }

    /**
     * Estimasi jatah keluaran generator soal.
     * Tingkat sulit + banyak nomor mudah kena finishReason MAX_TOKENS di 4096
     * (terutama model yang memakai thinking tokens di dalam maxOutputTokens).
     */
    private function quizMaxOutputTokens(int $jumlah, string $tingkat): int
    {
        $perItem = match ($tingkat) {
            'sulit' => 480,
            'sedang' => 340,
            default => 260,
        };

        return min($this->teacherMaxOutputTokens(), max(4096, ($jumlah * $perItem) + 1600));
    }

    /** Opsi generate untuk dokumen panjang Asisten Guru (RPM, soal). */
    private function teacherGenerateOptions(): array
    {
        return [
            'auto_continue_on_max_tokens' => (bool) config('ai.teacher.auto_continue_on_max_tokens', true),
            'max_continuations' => (int) config('ai.teacher.max_continuations', 2),
        ];
    }

    private function teacherMaxOutputTokens(int $fallback = 8192): int
    {
        return max(1024, (int) config('ai.teacher.max_output_tokens', $fallback));
    }

    /** Batas waktu PHP untuk generate dokumen panjang + auto-lanjut. */
    private function teacherExecutionTimeLimit(): int
    {
        $longTimeout = (int) config('ai.long_timeout');
        $maxCalls = 1 + max(0, (int) config('ai.teacher.max_continuations', 2));

        return ($longTimeout * $maxCalls) + 60;
    }

    /**
     * POST /ai/teacher/quiz/preview - render hasil soal jadi dokumen berformat.
     * Memakai parser + markup yang sama dengan export Word, jadi yang dilihat guru
     * = yang tercetak. Murni parsing lokal (tanpa panggil AI), maka tak kena rate limit.
     */
    public function previewQuiz(Request $request): JsonResponse
    {
        $data = $this->validatedQuizExport($request);
        $doc = QuizDocument::parse(SchoolLetterhead::ensurePrefix($data['content']));

        return response()->json([
            'ok' => true,
            'parsed' => $doc['parsed'],
            'html' => view('ai.teacher-quiz-preview', [
                'doc' => $doc,
                'content' => $doc['text'],
            ])->render(),
        ]);
    }

    /** POST /ai/teacher/quiz/export-word - export hasil soal yang sudah bisa diedit guru. */
    public function exportQuizWord(Request $request)
    {
        $data = $this->validatedQuizExport($request);

        $title = trim((string) ($data['title'] ?? '')) ?: 'Soal dari Asisten Guru';
        $safeName = Str::slug($title) ?: 'soal-asisten-ai';
        $fileName = $safeName.'-'.now()->format('Ymd-His').'.docx';
        $path = tempnam(sys_get_temp_dir(), 'ai-quiz-word-');

        if (! $path) {
            abort(500, 'Gagal membuat file Word.');
        }

        // Dokumen soal berformat dirender sebagai dokumen Word formal; selain itu paragraf polos.
        $doc = QuizDocument::parse(SchoolLetterhead::ensurePrefix($data['content']));
        $written = $doc['parsed']
            ? QuizDocxBuilder::write($path, $doc)
            : $this->writeDocx($path, $title, $doc['text'], false, false);

        if (! $written) {
            abort(500, 'Gagal membuat file Word.');
        }

        return $this->attachmentDownload($path, $fileName, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    /** POST /ai/teacher/quiz/export-pdf - export hasil soal ke PDF siap cetak. */
    public function exportQuizPdf(Request $request)
    {
        $data = $this->validatedQuizExport($request);

        $title = trim((string) ($data['title'] ?? '')) ?: 'Soal dari Asisten Guru';
        $fileName = (Str::slug($title) ?: 'soal-asisten-ai').'-'.now()->format('Ymd-His').'.pdf';

        // Konten berformat dirender lewat partial yang sama dengan pratinjau & Word;
        // konten bebas jatuh ke render teks polos.
        $doc = QuizDocument::parse(SchoolLetterhead::ensurePrefix($data['content']));

        $pdf = Pdf::loadView('ai.teacher-quiz-pdf', [
            'title' => $title,
            'content' => $doc['text'],
            'doc' => $doc,
        ])->setPaper('a4', 'portrait');

        // Stream PDF dengan header attachment — andal di browser mobile & WebView DownloadManager.
        return $pdf->download($fileName)->withHeaders($this->attachmentHeaders(
            $fileName,
            'application/pdf'
        ));
    }

    /** POST /ai/teacher/learning - generator RPM Learning. */
    public function learning(Request $request): JsonResponse
    {
        $built = $this->composeLearning($request);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        // Dokumen RPM utuh (+3 lampiran) butuh ~3.500 token dan ~45 detik.
        @set_time_limit($this->teacherExecutionTimeLimit());

        $visionImages = $built['vision_images'] ?? [];
        if (is_array($visionImages) && $visionImages !== []) {
            if ($blocked = $this->requireTeacherReady($request)) {
                return $blocked;
            }

            $user = $request->user();
            $userId = $user->uuid;
            $apiKey = $user->plainGeminiApiKey();
            $action = 'teacher_learning_'.$built['learning_tool'];

            if ($limited = $this->aiRateLimited($action, $userId)) {
                return $limited;
            }

            try {
                $result = $this->gemini->visionText($visionImages, $this->teacherGenerateOptions() + [
                    'api_key' => $apiKey,
                    'prompt' => $built['prompt'],
                    'system' => trim($built['system']."\n\n".$built['answer_style']),
                    'max_output_tokens' => $this->teacherMaxOutputTokens(),
                    'temperature' => 0.35,
                    'timeout' => (int) config('ai.long_timeout'),
                    'include_global_system_prompt' => $built['include_global_system_prompt'] ?? true,
                ]);
            } catch (RuntimeException $e) {
                $this->logAiUsage($userId, $action, config('ai.model'), 0, 0, 'error');

                return response()->json([
                    'ok' => false,
                    'message' => $e->getMessage(),
                    'quota' => $this->aiPublicQuotaUsage(),
                ], 502);
            }

            $answer = SchoolLetterhead::ensurePrefix($result['text']);
            $this->logAiGenerationUsage(
                $userId,
                $action,
                $result,
                'success',
            );
            $history = $this->storeHistory($userId, $built['history'], $answer);

            $payload = [
                'ok' => true,
                'answer' => $answer,
                'history' => $history,
                'quota' => $this->aiPublicQuotaUsage(),
            ];
            if (! empty($result['truncated'])) {
                $payload['warning'] = 'Jawaban AI masih terpotong setelah dilanjutkan otomatis. Kurangi cakupan topik atau lampiran lalu coba lagi. Teks parsial tetap disimpan.';
            }

            return response()->json($payload);
        }

        return $this->respond(
            $request,
            'teacher_learning_'.$built['learning_tool'],
            $built['system'],
            $built['prompt'],
            $this->teacherMaxOutputTokens(),
            $this->teacherGenerateOptions() + [
                'thinking_level' => 'low',
                'timeout' => (int) config('ai.long_timeout'),
                'retries' => 1,
                'answer_style' => $built['answer_style'],
                'include_global_system_prompt' => $built['include_global_system_prompt'] ?? true,
            ],
            $built['history'],
        );
    }

    /**
     * POST /ai/teacher/learning/preview - render hasil jadi dokumen RPM berformat tabel.
     * Memakai partial yang sama dengan export PDF, jadi yang dilihat guru = yang tercetak.
     * Murni parsing lokal (tanpa panggil AI), maka tak kena rate limit/audit.
     */
    public function previewLearning(Request $request): JsonResponse
    {
        $data = $this->validatedLearningExport($request);
        $doc = LearningDocument::parse(SchoolLetterhead::ensurePrefix($data['content']));

        return response()->json([
            'ok' => true,
            'parsed' => $doc['parsed'],
            'html' => view('ai.teacher-document-preview', [
                'doc' => $doc,
                'content' => $doc['text'],
            ])->render(),
        ]);
    }

    /** POST /ai/teacher/learning/export-word - export hasil RPM Learning ke Word. */
    public function exportLearningWord(Request $request)
    {
        $data = $this->validatedLearningExport($request);
        $title = $this->learningExportTitle($data);
        $fileName = ($this->safeFileBase($title) ?: 'perangkat-ajar-learning').'-'.now()->format('Ymd-His').'.docx';
        $path = tempnam(sys_get_temp_dir(), 'ai-learning-word-');

        if (! $path) {
            abort(500, 'Gagal membuat file Word.');
        }

        // Konten berformat RPM dirender sebagai tabel Word formal; selain itu paragraf polos.
        $doc = LearningDocument::parse(SchoolLetterhead::ensurePrefix($data['content']));
        $written = $doc['parsed']
            ? LearningDocxBuilder::write($path, $doc)
            : $this->writeDocx($path, $title, $doc['text'], false, false);

        if (! $written) {
            abort(500, 'Gagal membuat file Word.');
        }

        return $this->attachmentDownload($path, $fileName, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    /** POST /ai/teacher/learning/export-pdf - export hasil RPM Learning ke PDF. */
    public function exportLearningPdf(Request $request)
    {
        $data = $this->validatedLearningExport($request);
        $title = $this->learningExportTitle($data);
        $fileName = ($this->safeFileBase($title) ?: 'perangkat-ajar-learning').'-'.now()->format('Ymd-His').'.pdf';

        $doc = LearningDocument::parse(SchoolLetterhead::ensurePrefix($data['content']));

        $pdf = Pdf::loadView('ai.teacher-document-pdf', [
            'title' => $title,
            'content' => $doc['text'],
            'doc' => $doc,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($fileName)->withHeaders($this->attachmentHeaders(
            $fileName,
            'application/pdf'
        ));
    }

    /**
     * Unduhan file attachment yang andal di Chrome mobile & Android WebView (DownloadManager).
     * Header eksplisit Content-Disposition: attachment memicu setDownloadListener di APK.
     */
    private function attachmentDownload(string $path, string $fileName, string $contentType)
    {
        return response()->download($path, $fileName, $this->attachmentHeaders($fileName, $contentType))
            ->deleteFileAfterSend(true);
    }

    /** @return array<string, string> */
    private function attachmentHeaders(string $fileName, string $contentType): array
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName) ?: 'dokumen.bin';
        $utf8 = rawurlencode($fileName);

        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$ascii.'"; filename*=UTF-8\'\''.$utf8,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            // Bantu beberapa WebView mengenali unduhan (bukan navigasi HTML).
            'Content-Transfer-Encoding' => 'binary',
        ];
    }

    /** POST /ai/teacher/summary - perangkum materi. */
    public function summary(Request $request): JsonResponse
    {
        $built = $this->composeSummary($request);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        return $this->respond(
            $request,
            'teacher_summary',
            $built['system'],
            $built['prompt'],
            2048,
            ['answer_style' => $built['answer_style'] ?? null, 'thinking_level' => 'low'],
            $built['history'],
        );
    }

    /** POST /ai/teacher/feedback - Catatan Siswa (draf komentar hangat untuk siswa). */
    public function feedback(Request $request): JsonResponse
    {
        $built = $this->composeFeedback($request);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        return $this->respond(
            $request,
            'teacher_feedback',
            $built['system'],
            $built['prompt'],
            2048,
            ['answer_style' => $built['answer_style'] ?? null, 'thinking_level' => 'low'],
            $built['history'],
        );
    }

    /**
     * @return array{system:string,prompt:string,answer_style:string,history:array,title:string}|JsonResponse
     */
    private function composeQuiz(Request $request): array|JsonResponse
    {
        if (! $request->has('jenis_soal') && $request->filled('jenis')) {
            $legacyJenis = (string) $request->input('jenis');
            $request->merge([
                'jenis_soal' => self::LEGACY_QUIZ_TYPES[$legacyJenis] ?? [$legacyJenis],
            ]);
        }

        $allowedQuizTypes = implode(',', array_keys(self::QUIZ_TYPES));
        $maxMaterial = max(4000, (int) config('ai.max_input_chars', 8000) * 2);
        $data = $request->validate([
            // Topik kini SELALU wajib: dengan RAG, topik adalah kunci pencarian yang
            // menentukan bagian buku mana yang dipakai. Tanpa topik tidak ada yang
            // bisa dicari, dan kita kembali ke menebak dari halaman awal.
            'topik' => ['required', 'string', 'max:500'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:20'],
            'jenis_soal' => ['required', 'array', 'min:1', 'max:5'],
            'jenis_soal.*' => ['required', 'string', 'distinct', 'in:'.$allowedQuizTypes],
            'tingkat' => ['required', 'in:mudah,sedang,sulit'],
            'jenjang' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'document_uuid' => ['nullable', 'string', 'max:64'],
            'material_text' => ['nullable', 'string', 'max:'.$maxMaterial],
            'soal_bergambar' => ['sometimes', 'boolean'],
            'output_language' => TeacherOutputLanguage::validationRules(),
            'include_pinyin' => ['sometimes', 'boolean'],
        ]);
        $data['jenis_soal'] = array_values(array_unique($data['jenis_soal']));
        $data['soal_bergambar'] = $request->boolean('soal_bergambar');
        $outputLanguage = TeacherOutputLanguage::normalize($data['output_language'] ?? null);
        $includePinyin = $request->boolean('include_pinyin') && $outputLanguage === 'zh-CN';
        $langLine = TeacherOutputLanguage::promptLine($outputLanguage);
        if ($pinyinLine = TeacherOutputLanguage::pinyinLine($includePinyin)) {
            $langLine .= "\n".$pinyinLine;
        }

        $topik = trim((string) $data['topik']);
        $user = $request->user();

        // Materi: file kecil (inline) | file besar (RAG) | buku lama (document_uuid) | teks
        // hasil OCR foto halaman buku (material_text, dari alur "Foto buku" spt di RPM Learning).
        $document = null;
        $inlineMaterial = '';
        $materialSource = null;

        try {
            if ($request->hasFile('file')) {
                [$inlineMaterial, $document] = $this->materials->resolveUpload($user, $request->file('file'));
                $materialSource = 'file';
            } elseif (! empty($data['document_uuid'])) {
                $document = $this->materials->findOwned($user, (string) $data['document_uuid']);
                $materialSource = 'file';
            } elseif (trim((string) ($data['material_text'] ?? '')) !== '') {
                $inlineMaterial = trim((string) $data['material_text']);
                $materialSource = 'camera_ocr';
            }

            $material = $inlineMaterial;
            if ($material === '' && $document) {
                $material = $this->materials->retrieveForTopic($document, $topik, $user->uuid);
            }
        } catch (TeacherMaterialException $e) {
            return response()->json($e->toArray(), $e->httpStatus);
        }

        $jenis = $this->quizTypeSummary($data['jenis_soal']);
        $jenjang = ! empty($data['jenjang']) ? "untuk jenjang {$data['jenjang']}" : '';
        $formatInstruction = $this->quizFormatInstruction(
            (int) $data['jumlah'],
            $data['jenis_soal'],
            $data['tingkat'],
            $data['jenjang'] ?? null,
            $topik,
            (bool) $data['soal_bergambar'],
        );

        if ($material !== '') {
            $label = $materialSource === 'camera_ocr' ? 'MATERI SCAN BUKU' : 'MATERI FILE';
            $sumber = $materialSource === 'camera_ocr' ? 'scan/foto halaman buku' : 'file';
            $prompt = "Buat {$data['jumlah']} soal ({$jenis}) dengan tingkat kesulitan "
                ."{$data['tingkat']} {$jenjang} berdasarkan materi dari {$sumber} berikut.\n"
                ."Fokus topik: \"{$topik}\".\n"
                ."JANGAN keluar dari cakupan {$label}. Bila sebuah fakta tidak ada di "
                ."dalamnya, jangan mengarang — susun soal dari bagian yang tersedia saja.\n"
                .$langLine."\n\n"
                ."{$label}:\n{$material}\n\n"
                .$formatInstruction;
        } else {
            $prompt = "Buat {$data['jumlah']} soal ({$jenis}) dengan tingkat kesulitan "
                ."{$data['tingkat']} tentang topik: \"{$topik}\" {$jenjang}.\n"
                .$langLine."\n\n"
                .$formatInstruction;
        }

        $title = $topik;

        return [
            'system' => TeacherOutputLanguage::appendSystemNote((string) config('ai.teacher.quiz'), $outputLanguage),
            'prompt' => $prompt,
            'answer_style' => 'Tulis sebagai dokumen soal teks polos siap cetak sesuai format yang diminta. JANGAN memakai Markdown, heading #, atau bullet dekoratif.',
            'title' => (string) $title,
            'soal_bergambar' => (bool) $data['soal_bergambar'],
            'output_language' => $outputLanguage,
            'include_global_system_prompt' => TeacherOutputLanguage::usesGlobalSystemPrompt($outputLanguage),
            'history' => [
                'type' => 'quiz',
                'type_label' => 'Generator Soal',
                'title' => $title,
                'metadata' => [
                    'jumlah' => $data['jumlah'],
                    'jenis_soal' => $data['jenis_soal'],
                    'tingkat' => $data['tingkat'],
                    'jenjang' => $data['jenjang'] ?? null,
                    'soal_bergambar' => (bool) $data['soal_bergambar'],
                    'output_language' => $outputLanguage,
                    'include_pinyin' => $includePinyin,
                    'file' => $request->file('file')?->getClientOriginalName() ?? $document?->title,
                    'document_uuid' => $document?->uuid,
                    'source' => $materialSource ?? 'topic',
                    'via' => 'sims',
                ],
            ],
        ];
    }

    /**
     * @return array{system:string,prompt:string,answer_style:string,history:array,title:string,learning_tool:string}|JsonResponse
     */
    private function composeLearning(Request $request): array|JsonResponse
    {
        $maxMaterial = max(4000, (int) config('ai.max_input_chars', 8000) * 2);
        $data = $request->validate([
            'tool' => ['required', 'in:rpp'],
            'topik' => ['nullable', 'required_without_all:file,material_text,document_uuid', 'string', 'max:500'],
            'mapel' => ['nullable', 'string', 'max:100'],
            'jenjang' => ['nullable', 'string', 'max:100'],
            'durasi' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'document_uuid' => ['nullable', 'string', 'max:64'],
            'material_text' => ['nullable', 'string', 'max:'.$maxMaterial],
            'output_language' => TeacherOutputLanguage::validationRules(),
            'include_pinyin' => ['sometimes', 'boolean'],
        ]);

        $outputLanguage = TeacherOutputLanguage::normalize($data['output_language'] ?? null);
        $includePinyin = $request->boolean('include_pinyin') && $outputLanguage === 'zh-CN';
        $langLine = TeacherOutputLanguage::promptLine($outputLanguage);
        if ($pinyinLine = TeacherOutputLanguage::pinyinLine($includePinyin)) {
            $langLine .= "\n".$pinyinLine;
        }
        if ($outputLanguage === 'zh-CN') {
            $langLine .= "\n".TeacherOutputLanguage::rpmMandarinHints();
        }
        $user = $request->user();
        $document = null;
        $inlineMaterial = '';
        $materialSource = null;

        try {
            if ($request->hasFile('file')) {
                [$inlineMaterial, $document] = $this->materials->resolveUpload($user, $request->file('file'));
                $materialSource = 'file';
            } elseif (! empty($data['document_uuid'])) {
                $document = $this->materials->findOwned($user, (string) $data['document_uuid']);
                $materialSource = 'file';
            } elseif (trim((string) ($data['material_text'] ?? '')) !== '') {
                $inlineMaterial = trim((string) $data['material_text']);
                $materialSource = 'camera_ocr';
            }

            $material = $inlineMaterial;
            if ($material === '' && $document) {
                $topikForRag = trim((string) ($data['topik'] ?? ''));
                if ($topikForRag === '') {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Topik wajib diisi saat memakai buku/file besar agar bagian yang relevan bisa dicari.',
                        // Supaya UI bisa resume tanpa unggah ulang setelah resolveUpload.
                        'document_uuid' => $document->uuid,
                    ], 422);
                }
                $material = $this->materials->retrieveForTopic($document, $topikForRag, $user->uuid);
            }
        } catch (TeacherMaterialException $e) {
            return response()->json($e->toArray(), $e->httpStatus);
        }

        $toolLabel = $this->learningToolLabel($data['tool']);
        $topik = trim((string) ($data['topik'] ?? ''));
        $title = $topik !== ''
            ? $topik
            : ($materialSource === 'camera_ocr'
                ? 'RPM dari foto buku'
                : 'RPM dari file '.$request->file('file')?->getClientOriginalName());
        $details = array_filter([
            ! empty($data['mapel']) ? "Mata pelajaran: {$data['mapel']}" : null,
            ! empty($data['jenjang']) ? "Jenjang/kelas: {$data['jenjang']}" : null,
            ! empty($data['durasi']) ? "Alokasi waktu: {$data['durasi']}" : null,
        ]);
        $detailText = $details ? implode("\n", $details)."\n" : '';
        $topicLine = $topik !== '' ? "Fokus/topik RPM: \"{$topik}\".\n" : '';

        if ($material !== '') {
            $label = $materialSource === 'camera_ocr' ? 'MATERI SCAN BUKU' : 'MATERI FILE';
            $sumber = $materialSource === 'camera_ocr' ? 'scan/foto halaman buku' : 'file';
            $prompt = "Buat {$toolLabel} siap pakai untuk guru berdasarkan materi dari {$sumber} berikut.\n"
                .$topicLine
                .$detailText
                .$langLine."\n"
                ."JANGAN keluar dari cakupan {$label}. Jika ada informasi yang belum ada di materi, gunakan placeholder yang jelas, bukan mengarang.\n\n"
                ."{$label}:\n{$material}\n\n"
                .$this->learningFormatInstruction($data['tool']);
        } else {
            $prompt = "Buat {$toolLabel} siap pakai untuk guru dengan topik: \"{$topik}\".\n"
                .$detailText
                .$langLine."\n\n"
                .$this->learningFormatInstruction($data['tool']);
        }

        return [
            'system' => TeacherOutputLanguage::appendSystemNote((string) config('ai.teacher.learning'), $outputLanguage),
            'prompt' => $prompt,
            'answer_style' => 'Tulis sebagai dokumen teks polos siap cetak. JANGAN memakai Markdown '
                .'(tanpa **tebal**, tanpa heading #, tanpa tabel pipa selain yang diminta format).',
            'title' => (string) $title,
            'learning_tool' => $data['tool'],
            'output_language' => $outputLanguage,
            'include_global_system_prompt' => TeacherOutputLanguage::usesGlobalSystemPrompt($outputLanguage),
            'history' => [
                'type' => $data['tool'],
                'type_label' => $toolLabel,
                'title' => $title,
                'metadata' => [
                    'mapel' => $data['mapel'] ?? null,
                    'jenjang' => $data['jenjang'] ?? null,
                    'durasi' => $data['durasi'] ?? null,
                    'output_language' => $outputLanguage,
                    'include_pinyin' => $includePinyin,
                    'file' => $request->file('file')?->getClientOriginalName() ?? $document?->title,
                    'document_uuid' => $document?->uuid,
                    'source' => $materialSource ?? 'topic',
                    'via' => 'sims',
                ],
            ],
        ];
    }

    /**
     * Siapkan binary gambar untuk OCR: resize edge bila perlu, JPEG quality tinggi.
     *
     * @return array{binary:string,mime:string}|null
     */
    private function prepareOcrImageBinary(string $path, string $mime): ?array
    {
        if (! is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $mime = strtolower($mime);
        if ($mime === 'image/jpg') {
            $mime = 'image/jpeg';
        }

        $maxEdge = max(800, (int) config('ai.ocr.max_edge', 1920));
        $quality = max(80, min(95, (int) config('ai.ocr.jpeg_quality', 90)));

        if (! function_exists('imagecreatefromstring')) {
            return ['binary' => $raw, 'mime' => in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mime : 'image/jpeg'];
        }

        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            // Jangan kirim biner non-gambar ke provider eksternal.
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);

            return null;
        }

        $scale = 1.0;
        $long = max($w, $h);
        if ($long > $maxEdge) {
            $scale = $maxEdge / $long;
        }

        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        if ($scale < 1.0) {
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        ob_start();
        imagejpeg($src, null, $quality);
        $binary = ob_get_clean();
        imagedestroy($src);

        if ($binary === false || $binary === '') {
            return ['binary' => $raw, 'mime' => 'image/jpeg'];
        }

        return ['binary' => $binary, 'mime' => 'image/jpeg'];
    }

    /**
     * @return array{system:string,prompt:string,history:array,title:string}|JsonResponse
     */
    private function composeSummary(Request $request): array|JsonResponse
    {
        $data = $request->validate([
            'materi' => ['required', 'string', 'max:'.config('ai.max_input_chars')],
        ]);

        $prompt = SchoolLetterhead::asPromptBlock()
            ."\n\nRangkum materi berikut menjadi poin-poin ringkas untuk siswa. "
            ."Mulai jawaban dengan kop surat di atas, lalu judul RANGKUMAN MATERI, lalu isi.\n\n"
            .$data['materi'];

        return [
            'system' => (string) config('ai.teacher.summary'),
            'prompt' => $prompt,
            'answer_style' => SchoolLetterhead::asPromptBlock()
                ."\nTulis teks polos berstruktur. Mulai dengan kop, lalu RANGKUMAN MATERI, lalu poin-poin. Tanpa Markdown.",
            'title' => Str::limit($data['materi'], 90),
            'history' => [
                'type' => 'summary',
                'type_label' => 'Perangkum Materi',
                'title' => Str::limit($data['materi'], 90),
                'metadata' => [
                    'panjang_materi' => mb_strlen($data['materi']),
                    'via' => 'sims',
                ],
            ],
        ];
    }

    /**
     * @return array{system:string,prompt:string,history:array,title:string}|JsonResponse
     */
    private function composeFeedback(Request $request): array|JsonResponse
    {
        $data = $request->validate([
            'konteks' => ['required', 'string', 'max:'.config('ai.max_input_chars')],
            'nama' => ['nullable', 'string', 'max:100'],
        ]);

        $nama = $data['nama'] ? "untuk siswa bernama {$data['nama']}" : '';
        $prompt = SchoolLetterhead::asPromptBlock()
            ."\n\nSusun Catatan Siswa {$nama} berdasarkan konteks berikut. "
            ."Tulis dengan nada hangat, membangun, dan mudah diterima siswa/orang tua. "
            ."Mulai jawaban dengan kop surat di atas, lalu judul CATATAN SISWA, lalu isi.\n\n"
            .$data['konteks'];
        $title = ! empty($data['nama']) ? 'Catatan untuk '.$data['nama'] : Str::limit($data['konteks'], 90);

        return [
            'system' => (string) config('ai.teacher.feedback'),
            'prompt' => $prompt,
            'answer_style' => SchoolLetterhead::asPromptBlock()
                ."\nTulis teks polos. Mulai dengan kop, lalu CATATAN SISWA, lalu isi. Tanpa Markdown.",
            'title' => $title,
            'history' => [
                'type' => 'feedback',
                'type_label' => 'Catatan Siswa',
                'title' => $title,
                'metadata' => [
                    'nama' => $data['nama'] ?? null,
                    'via' => 'sims',
                ],
            ],
        ];
    }

    /** Pipeline bersama: rate limit -> Gemini (key pribadi) -> audit -> JSON. */
    private function respond(Request $request, string $feature, string $system, string $prompt, int $maxOutputTokens = 2048, array $options = [], ?array $historyData = null): JsonResponse
    {
        if ($blocked = $this->requireTeacherReady($request)) {
            return $blocked;
        }

        $user = $request->user();
        $userId = $user->uuid;
        $apiKey = $user->plainGeminiApiKey();

        if ($limited = $this->aiRateLimited($feature, $userId)) {
            return $limited;
        }

        try {
            $result = $this->gemini->generate($prompt, $options + [
                'system' => $system,
                'max_output_tokens' => $maxOutputTokens, // keluaran guru cenderung lebih panjang
                'api_key' => $apiKey,
            ]);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, $feature, config('ai.model'), 0, 0, 'error');

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'quota' => $this->aiPublicQuotaUsage(),
            ], 502);
        }

        $this->logAiGenerationUsage(
            $userId,
            $feature,
            $result,
            'success',
        );

        $answer = SchoolLetterhead::ensurePrefix($result['text']);

        $history = $historyData !== null
            ? $this->storeHistory($userId, $historyData, $answer)
            : null;

        $payload = [
            'ok' => true,
            'answer' => $answer,
            'history' => $history,
            'quota' => $this->aiPublicQuotaUsage(),
        ];
        if (! empty($result['truncated'])) {
            $payload['warning'] = 'Jawaban AI masih terpotong setelah dilanjutkan otomatis. Kurangi cakupan topik atau lampiran lalu coba lagi. Teks parsial tetap disimpan.';
        }

        return response()->json($payload);
    }


    private function storeHistory(string $userId, array $data, string $answer): array
    {
        $history = AiTeacherHistory::create([
            'user_uuid' => $userId,
            'type' => $data['type'],
            'type_label' => $data['type_label'],
            // Str::limit menambah '...' (3 char) di ujung, jadi batas angkanya dikurangi 3
            // agar hasil ≤ lebar kolom (title VARCHAR(180), excerpt VARCHAR(500)). Tanpa ini
            // MySQL strict menolak insert (SQLSTATE 22001 / error 1406); SQLite dev tidak
            // menegakkan panjang VARCHAR sehingga bug ini lolos di lokal.
            'title' => Str::limit(trim((string) $data['title']) ?: $data['type_label'], 177),
            'excerpt' => Str::limit($this->plainExcerpt($answer), 497),
            'metadata' => array_filter($data['metadata'] ?? [], fn ($value) => $value !== null && $value !== ''),
            'answer' => $answer,
        ]);

        return $this->historyPayload($history);
    }

    private function historyPayload(AiTeacherHistory $history): array
    {
        return [
            'uuid' => $history->uuid,
            'type' => $history->type,
            'type_label' => $history->type_label,
            'title' => $history->title,
            'excerpt' => $history->excerpt,
            'answer' => $history->answer,
            'metadata' => $history->metadata ?? [],
            'created_at' => $history->created_at?->toIso8601String(),
            'created_at_human' => $history->created_at?->locale('id')->diffForHumans(),
        ];
    }

    private function plainExcerpt(string $answer): string
    {
        $text = strip_tags($answer);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim((string) $text);
    }

    private function validatedQuizExport(Request $request): array
    {
        return $request->validate([
            'content' => ['required', 'string', 'max:50000'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);
    }

    private function validatedLearningExport(Request $request): array
    {
        return $request->validate([
            'tool' => ['required', 'in:rpp'],
            'title' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:80000'],
        ]);
    }

    private function learningExportTitle(array $data): string
    {
        $title = trim((string) ($data['title'] ?? ''));

        return $title !== '' ? $title : $this->learningToolLabel($data['tool']);
    }

    private function quizTypeSummary(array $jenisSoal): string
    {
        return implode(', ', array_map(fn (string $type) => self::QUIZ_TYPES[$type], $jenisSoal));
    }

    private function quizSectionTemplates(array $jenisSoal): string
    {
        $templates = [
            'pg_kompleks' => "Bagian %s - Pilihan Ganda Kompleks\n[nomor]. [soal]\nA. [opsi]\nB. [opsi]\nC. [opsi]\nD. [opsi]\nPetunjuk: pilih semua jawaban yang benar.",
            'pg' => "Bagian %s - Pilihan Ganda\n[nomor]. [soal]\nA. [opsi]\nB. [opsi]\nC. [opsi]\nD. [opsi]",
            'benar_salah' => "Bagian %s - Benar/Salah\n[nomor]. [pernyataan yang harus dinilai benar atau salah]",
            'mencocokkan' => "Bagian %s - Mencocokkan\n[nomor]. Cocokkan pernyataan pada Kolom A dengan jawaban pada Kolom B.\nKolom A: [daftar pernyataan bernomor]\nKolom B: [daftar pilihan berhuruf]",
            'isian' => "Bagian %s - Isian\n[nomor]. [kalimat soal dengan jawaban singkat]\nJawaban: ______________________________",
        ];

        $sections = [];
        foreach ($jenisSoal as $index => $type) {
            $letter = chr(65 + $index);
            $sections[] = sprintf($templates[$type], $letter);
        }

        return implode("\n\n", $sections);
    }

    private function quizAnswerKeyTemplates(array $jenisSoal): string
    {
        $templates = [
            'pg_kompleks' => "Pilihan Ganda Kompleks\n[nomor]. A, C",
            'pg' => "Pilihan Ganda\n[nomor]. A",
            'benar_salah' => "Benar/Salah\n[nomor]. Benar",
            'mencocokkan' => "Mencocokkan\n[nomor]. 1-B, 2-A, 3-C",
            'isian' => "Isian\n[nomor]. [jawaban singkat]",
        ];

        return implode("\n\n", array_map(fn (string $type) => $templates[$type], $jenisSoal));
    }

    private function quizTypeRules(array $jenisSoal): string
    {
        $rules = [
            'pg_kompleks' => 'Pilihan Ganda Kompleks memakai opsi A-D dan boleh memiliki lebih dari satu jawaban benar; kunci ditulis seperti "1. A, C".',
            'pg' => 'Pilihan Ganda memakai opsi A-D dan hanya satu jawaban benar; kunci ditulis seperti "1. A".',
            'benar_salah' => 'Benar/Salah berupa pernyataan yang dinilai Benar atau Salah; kunci ditulis "Benar" atau "Salah".',
            'mencocokkan' => 'Mencocokkan berisi pasangan Kolom A dan Kolom B; kunci ditulis dengan pasangan seperti "1-B, 2-A".',
            'isian' => 'Isian meminta jawaban singkat; sediakan ruang jawaban dan kunci jawaban singkat.',
        ];

        return '- '.implode("\n- ", array_map(fn (string $type) => $rules[$type], $jenisSoal));
    }

    private function quizFormatInstruction(int $jumlah, array $jenisSoal, string $tingkat, ?string $jenjang, string $topik, bool $soalBergambar = false): string
    {
        $kelas = trim((string) $jenjang) !== '' ? trim((string) $jenjang) : '[KELAS / SEMESTER]';
        $topikJudul = trim($topik) !== '' ? mb_strtoupper($topik) : '[TOPIK]';
        $tingkatLabel = Str::title($tingkat);
        $jenisLabel = $this->quizTypeSummary($jenisSoal);
        $sectionTemplates = $this->quizSectionTemplates($jenisSoal);
        $answerKeyTemplates = $this->quizAnswerKeyTemplates($jenisSoal);
        $typeRules = $this->quizTypeRules($jenisSoal);
        $gambarRules = $soalBergambar
            ? <<<'GAMBAR'
- Wajib menyertakan soal bergambar: pada SEMUA atau sebagian besar nomor, sisipkan penanda tepat di bawah teks soal (sebelum opsi A/B/C/D) dengan format:
  [GAMBAR: deskripsi visual singkat yang spesifik]
  Contoh: [GAMBAR: diagram sirkulasi darah manusia dengan label atrium dan ventrikel]
- Deskripsi harus cukup jelas untuk digambar AI (objek, label, gaya diagram/sketsa sekolah).
- Jangan menulis Markdown gambar, URL, atau base64. Hanya penanda [GAMBAR: ...].
- Jangan menaruh penanda gambar di bagian Kunci Jawaban.
GAMBAR
            : '- Jangan menyisipkan gambar, Markdown gambar, atau URL gambar.';

        $kop = SchoolLetterhead::asPlainText();

        return <<<TXT
FORMAT WAJIB mengikuti contoh file soal-agama-buddha.docx. Tulis teks polos dengan urutan ini, tanpa Markdown:

{$kop}
SOAL EVALUASI [MATA PELAJARAN / TOPIK]
{$kelas} - Tingkat Kesulitan {$tingkatLabel}

Mata Pelajaran : [isi mata pelajaran/topik: {$topikJudul}]
Kelas / Semester : {$kelas}
Nama : ...............................................................
Nilai : ...............................................................

Petunjuk Pengerjaan
Kerjakan soal sesuai instruksi pada setiap bagian.
Periksa kembali jawaban sebelum dikumpulkan.

{$sectionTemplates}

Kunci Jawaban & Pedoman Penilaian
(Untuk Guru)

{$answerKeyTemplates}

ATURAN:
- Jenis soal yang dibuat hanya: {$jenisLabel}.
- Total soal harus {$jumlah} nomor, dibagi proporsional jika ada lebih dari satu jenis soal.
- Nomor soal berurutan dari Bagian A sampai bagian terakhir.
{$typeRules}
{$gambarRules}
- Salin PERSIS baris kop surat di atas (dari identitas sekolah di SIMS). Jangan mengarang yayasan/alamat/telp lain.
- Jika data mata pelajaran/kelas/semester tidak tersedia, gunakan placeholder jelas.
- Jangan menulis pengantar atau catatan di luar dokumen soal.
TXT;
    }

    private function learningFormatInstruction(string $tool): string
    {
        $kop = SchoolLetterhead::asPlainText();
        $namaSekolah = SchoolLetterhead::schoolName();
        $kepala = SchoolLetterhead::kepalaSekolah();
        $nipKepala = SchoolLetterhead::nipKepala();

        return <<<TXT
Ikuti format RPM resmi sekolah PERSIS (hasil export Word/PDF harus sama bentuknya). Tulis teks polos tanpa Markdown (#, **, ```), tanpa pembuka/penutup di luar dokumen.

KOP WAJIB (salin PERSIS dari identitas sekolah di SIMS; jangan diganti atau dikarang):
{$kop}

JUDUL:
PERENCANAAN PEMBELAJARAN MENDALAM
"[JUDUL TOPIK HURUF KAPITAL DALAM TANDA KUTIP]"

IDENTITAS (satu label per baris, spasi sebelum titik dua):
SEKOLAH : {$namaSekolah}
NAMA GURU : [nama]
MATA PELAJARAN : [mapel]
KELAS / SEMESTER : [kelas] / [semester]
ALOKASI WAKTU : [contoh: 2 JP (2 x 40 Menit)]

IDENTIFIKASI
Murid:
[paragraf kondisi murid]
Materi:
[nama materi]
Dimensi Profil Lulusan (DPL):
Dimensi profil lulusan yang akan dicapai dalam pembelajaran:
☑ DPL 1 Keimanan dan ketakwaan terhadap Tuhan Yang Maha Esa
☑ DPL 2 Kewargaan
☑ DPL 3 Penalaran Kritis
☐ DPL 4 Kreativitas
☑ DPL 5 Kolaborasi
☑ DPL 6 Kemandirian
☑ DPL 7 Kesehatan
☑ DPL 8 Komunikasi
(centang ☑ hanya yang relevan; sisanya ☐ — selalu 8 baris)

DESAIN PEMBELAJARAN
Capaian Pembelajaran:
[paragraf]
Lintas Disiplin Ilmu:
[disiplin]: [isi]
Tujuan Pembelajaran:
1. ...
2. ...
Topik Pembelajaran:
1. ...
2. ...
Praktik Pedagogis:
[pendekatan/model]
Kemitraan Pembelajaran:
1. ...
2. ...
Lingkungan Pembelajaran:
Lingkungan Pembelajaran Terintegrasi:
Ruang Fisik (Lingkungan Nyata):
• ...
Ruang Virtual (Pembelajaran Kolaboratif):
• ...
Budaya Belajar:
• ...
Pemanfaatan Digital:
1. Perencanaan: ...
2. Pelaksanaan: ...
3. Asesmen: ...
4. Media Sosial & Kampanye Digital: ...

PENGALAMAN BELAJAR
AWAL
(Berkesadaran, Bermakna, dan Menggembirakan)
✓ [kegiatan] (Berkesadaran)
✓ [kegiatan]
✓ [pertanyaan pemantik]:
"[pertanyaan 1]"
"[pertanyaan 2]"
INTI (Berkesadaran, Bermakna, dan Menggembirakan)
MEMAHAMI
(Berkesadaran dan Bermakna)
✓ ...
MENGAPLIKASI
(Berkesadaran, Bermakna, dan Menggembirakan)
✓ ...
MEREFLEKSI
(Berkesadaran, Bermakna, dan Menggembirakan)
✓ ...
PENUTUP (Berkesadaran, Bermakna, dan Menggembirakan)
✓ ...
✓ [refleksi]:
"[pertanyaan]"
"[pertanyaan]"
"[pertanyaan]"

ASESMEN PEMBELAJARAN
Asesmen pada Awal Pembelajaran:
[deskripsi]
• Jenis Soal: ...
• Jumlah Soal: ...
• Tujuan: ...
Asesmen pada Proses Pembelajaran:
[deskripsi observasi]
Asesmen pada Akhir Pembelajaran:
[deskripsi]
a. ...
b. ...

TANDA TANGAN:
[Tempat], [tanggal]
Mengetahui, | Guru Mata Pelajaran
Kepala Sekolah |
{$kepala} | [Nama Guru]
NIK. {$nipKepala} | NIK. [nomor/titik-titik]

LAMPIRAN 1 : ASESMEN AWAL PEMBELAJARAN
• Materi : ...
• Kelas : ...
• Jenis Soal : ...
• Tujuan : ...
A. Soal Pilihan Ganda (3 soal)
1. ...
a. ...
b. ...
c. ...
d. ...
(minimal 3 soal)

LAMPIRAN 2 : ASESMEN PADA PROSES PEMBELAJARAN
Tujuan Pembelajaran:
1. ...
2. ...
Kompetensi | Baru Mulai | Berkembang | Cakap | Mahir
[kompetensi] | [baru mulai] | [berkembang] | [cakap] | [mahir]
(minimal 4 baris kompetensi)
Keterangan:
• Baru Mulai: ...
• Berkembang: ...
• Cakap: ...
• Mahir: ...

LAMPIRAN 3 : ASESMEN PADA AKHIR PEMBELAJARAN
• Kisi-Kisi : ...
• Materi : ...
Soal Pilihan Ganda
1. ...
a. ...
b. ...
c. ...
d. ...
(minimal 10 soal)

Isi harus sesuai topik/mapel/kelas yang diminta. Jangan mengarang identitas yang tidak diberikan — pakai placeholder [Nama Guru] bila belum ada. Kop surat dan nama kepala sekolah sudah diisi dari data SIMS.
TXT;
    }

    private function learningToolLabel(string $tool): string
    {
        return 'RPM Learning';
    }

    private function safeFileBase(string $title): string
    {
        return Str::slug($title) ?: 'perangkat-ajar-learning';
    }

    private function writeDocx(string $path, string $title, string $content, bool $includeTitle = true, bool $includeGeneratedNote = true): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', $this->wordDocumentXml($title, $content, $includeTitle, $includeGeneratedNote));

        return $zip->close();
    }

    private function wordDocumentXml(string $title, string $content, bool $includeTitle = true, bool $includeGeneratedNote = true): string
    {
        $lines = preg_split('/\R/u', trim($content)) ?: [];
        $paragraphs = '';
        if ($includeTitle) {
            $paragraphs .= $this->wordParagraph($title, true);
        }
        if ($includeGeneratedNote) {
            $paragraphs .= $this->wordParagraph('Dibuat dari Asisten Guru pada '.now()->format('d/m/Y H:i'));
        }
        if ($paragraphs !== '') {
            $paragraphs .= '<w:p/>';
        }

        foreach ($lines as $line) {
            $paragraphs .= $this->wordParagraph($line === '' ? ' ' : $line);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .$paragraphs
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    private function wordParagraph(string $text, bool $bold = false): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $runProperties = $bold ? '<w:rPr><w:b/><w:sz w:val="32"/></w:rPr>' : '';

        return '<w:p><w:r>'.$runProperties.'<w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>';
    }

    /** DELETE /ai/teacher/materials/{uuid} - Batalkan dan hapus materi guru. */
    public function cancelMaterial(string $uuid, Request $request): JsonResponse
    {
        try {
            $this->materials->cancelMaterial($uuid, $request->user());

            return response()->json([
                'ok' => true,
                'message' => 'Pemrosesan materi berhasil dibatalkan dan dibersihkan.',
            ]);
        } catch (TeacherMaterialException $e) {
            return response()->json($e->toArray(), $e->httpStatus);
        }
    }

    private function extractQuizDocumentText(string $path, string $extension, bool $preserveNewlines = false): string
    {
        // Ekstraksi dipusatkan di DocumentText agar RagService (ingest RAG) membaca
        // dokumen dengan cara yang persis sama seperti controller ini.
        return DocumentText::extract($path, $extension, $preserveNewlines);
    }
}
