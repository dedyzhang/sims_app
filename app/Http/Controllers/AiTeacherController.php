<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Models\AiTeacherHistory;
use App\Services\GeminiService;
use App\Support\LearningDocument;
use App\Support\LearningDocxBuilder;
use App\Support\QuizDocument;
use App\Support\QuizDocxBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/*
| Asisten Guru (FASE 3). Panel berisi 3 tool untuk mempercepat pekerjaan guru:
| Generator Soal, Perangkum Materi, dan Draft Feedback. Semua memanggil Gemini
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

    public function __construct(private GeminiService $gemini) {}

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

        return view('ai.teacher', [
            'histories' => $histories,
            'quotaUsage' => $this->aiPublicQuotaUsage(true),
            'canViewQuotaUsage' => false,
        ]);
    }

    /** GET /ai/teacher/quota — snapshot kuota live (dipoll UI). */
    public function quota(Request $request): JsonResponse
    {
        $fresh = $request->boolean('fresh');

        return response()->json([
            'ok' => true,
            'quota' => $this->aiPublicQuotaUsage($fresh),
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
        if (! $request->has('jenis_soal') && $request->filled('jenis')) {
            $legacyJenis = (string) $request->input('jenis');
            $request->merge([
                'jenis_soal' => self::LEGACY_QUIZ_TYPES[$legacyJenis] ?? [$legacyJenis],
            ]);
        }

        $allowedQuizTypes = implode(',', array_keys(self::QUIZ_TYPES));
        $data = $request->validate([
            'topik' => ['nullable', 'required_without:file', 'string', 'max:500'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:20'],
            'jenis_soal' => ['required', 'array', 'min:1', 'max:5'],
            'jenis_soal.*' => ['required', 'string', 'distinct', 'in:'.$allowedQuizTypes],
            'tingkat' => ['required', 'in:mudah,sedang,sulit'],
            'jenjang' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);
        $data['jenis_soal'] = array_values(array_unique($data['jenis_soal']));

        $documentText = '';
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $documentText = $this->extractQuizDocumentText($file->getRealPath(), $file->getClientOriginalExtension());

            if ($documentText === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Teks tidak dapat diekstrak dari file. Pastikan PDF bukan hasil scan/gambar dan file Word berisi teks.',
                ], 422);
            }
        }

        $jenis = $this->quizTypeSummary($data['jenis_soal']);
        $topik = trim((string) ($data['topik'] ?? ''));
        $jenjang = ! empty($data['jenjang']) ? "untuk jenjang {$data['jenjang']}" : '';

        $formatInstruction = $this->quizFormatInstruction((int) $data['jumlah'], $data['jenis_soal'], $data['tingkat'], $data['jenjang'] ?? null, $topik);

        if ($documentText !== '') {
            $maxChars = (int) config('ai.max_input_chars');
            $material = mb_substr($documentText, 0, $maxChars);
            $topicLine = $topik !== '' ? "Fokus topik: \"{$topik}\".\n" : '';

            $prompt = "Buat {$data['jumlah']} soal ({$jenis}) dengan tingkat kesulitan "
                ."{$data['tingkat']} {$jenjang} berdasarkan materi dari file berikut.\n"
                .$topicLine
                ."MATERI FILE:\n{$material}\n\n"
                .$formatInstruction;
        } else {
            $prompt = "Buat {$data['jumlah']} soal ({$jenis}) dengan tingkat kesulitan "
                ."{$data['tingkat']} tentang topik: \"{$topik}\" {$jenjang}.\n\n"
                .$formatInstruction;
        }

        return $this->respond($request, 'teacher_quiz', config('ai.teacher.quiz'), $prompt, 4096, [
            'answer_style' => 'Tulis sebagai dokumen soal teks polos siap cetak sesuai format yang diminta. JANGAN memakai Markdown, heading #, atau bullet dekoratif.',
        ], [
            'type' => 'quiz',
            'type_label' => 'Generator Soal',
            'title' => $topik !== '' ? $topik : 'Soal dari file '.$request->file('file')?->getClientOriginalName(),
            'metadata' => [
                'jumlah' => $data['jumlah'],
                'jenis_soal' => $data['jenis_soal'],
                'tingkat' => $data['tingkat'],
                'jenjang' => $data['jenjang'] ?? null,
                'file' => $request->file('file')?->getClientOriginalName(),
            ],
        ]);
    }

    /**
     * POST /ai/teacher/quiz/preview - render hasil soal jadi dokumen berformat.
     * Memakai parser + markup yang sama dengan export Word, jadi yang dilihat guru
     * = yang tercetak. Murni parsing lokal (tanpa panggil AI), maka tak kena rate limit.
     */
    public function previewQuiz(Request $request): JsonResponse
    {
        $data = $this->validatedQuizExport($request);
        $doc = QuizDocument::parse($data['content']);

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

        $title = trim((string) ($data['title'] ?? '')) ?: 'Soal dari AI Asisten SIMS';
        $safeName = Str::slug($title) ?: 'soal-asisten-ai';
        $fileName = $safeName.'-'.now()->format('Ymd-His').'.docx';
        $path = tempnam(sys_get_temp_dir(), 'ai-quiz-word-');

        if (! $path) {
            abort(500, 'Gagal membuat file Word.');
        }

        // Dokumen soal berformat dirender sebagai dokumen Word formal; selain itu paragraf polos.
        $doc = QuizDocument::parse($data['content']);
        $written = $doc['parsed']
            ? QuizDocxBuilder::write($path, $doc)
            : $this->writeDocx($path, $title, $doc['text'], false, false);

        if (! $written) {
            abort(500, 'Gagal membuat file Word.');
        }

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /** POST /ai/teacher/quiz/export-pdf - export hasil soal ke PDF siap cetak. */
    public function exportQuizPdf(Request $request)
    {
        $data = $this->validatedQuizExport($request);

        $title = trim((string) ($data['title'] ?? '')) ?: 'Soal dari AI Asisten SIMS';
        $fileName = (Str::slug($title) ?: 'soal-asisten-ai').'-'.now()->format('Ymd-His').'.pdf';

        // Konten berformat dirender lewat partial yang sama dengan pratinjau & Word;
        // konten bebas jatuh ke render teks polos.
        $doc = QuizDocument::parse($data['content']);

        $pdf = Pdf::loadView('ai.teacher-quiz-pdf', [
            'title' => $title,
            'content' => $doc['text'],
            'doc' => $doc,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($fileName);
    }

    /** POST /ai/teacher/learning - generator RPM Learning. */
    public function learning(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tool' => ['required', 'in:rpp'],
            'topik' => ['nullable', 'required_without:file', 'string', 'max:500'],
            'mapel' => ['nullable', 'string', 'max:100'],
            'jenjang' => ['nullable', 'string', 'max:100'],
            'durasi' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $documentText = '';
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $documentText = $this->extractQuizDocumentText($file->getRealPath(), $file->getClientOriginalExtension(), true);

            if ($documentText === '') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Teks tidak dapat diekstrak dari file. Pastikan PDF bukan hasil scan/gambar dan file Word berisi teks.',
                ], 422);
            }
        }

        $toolLabel = $this->learningToolLabel($data['tool']);
        $topik = trim((string) ($data['topik'] ?? ''));
        $title = $topik !== '' ? $topik : 'RPM dari file '.$request->file('file')?->getClientOriginalName();
        $details = array_filter([
            ! empty($data['mapel']) ? "Mata pelajaran: {$data['mapel']}" : null,
            ! empty($data['jenjang']) ? "Jenjang/kelas: {$data['jenjang']}" : null,
            ! empty($data['durasi']) ? "Alokasi waktu: {$data['durasi']}" : null,
        ]);
        $detailText = $details ? implode("\n", $details)."\n" : '';

        if ($documentText !== '') {
            $maxChars = (int) config('ai.max_input_chars');
            $material = mb_substr($documentText, 0, $maxChars);
            $topicLine = $topik !== '' ? "Fokus/topik RPM: \"{$topik}\".\n" : '';

            $prompt = "Buat {$toolLabel} siap pakai untuk guru berdasarkan materi dari file berikut.\n"
                .$topicLine
                .$detailText
                ."Gunakan Bahasa Indonesia baku, praktis, dan langsung bisa direview guru.\n"
                ."JANGAN keluar dari cakupan MATERI FILE. Jika ada informasi yang belum ada di file, gunakan placeholder yang jelas, bukan mengarang.\n\n"
                ."MATERI FILE:\n{$material}\n\n"
                .$this->learningFormatInstruction($data['tool']);
        } else {
            $prompt = "Buat {$toolLabel} siap pakai untuk guru dengan topik: \"{$topik}\".\n"
                .$detailText
                ."Gunakan Bahasa Indonesia baku, praktis, dan langsung bisa direview guru.\n\n"
                .$this->learningFormatInstruction($data['tool']);
        }
        // Dokumen RPM utuh (+3 lampiran) butuh ~3.500 token dan ~45 detik. Jatah token
        // dibagi dengan token "berpikir" model, jadi porsi berpikir ditekan agar dokumen
        // tidak terpotong di tengah; batas eksekusi PHP dinaikkan agar tak fatal duluan.
        @set_time_limit((int) config('ai.long_timeout') + 60);

        return $this->respond($request, 'teacher_learning_'.$data['tool'], config('ai.teacher.learning'), $prompt, 8192, [
            'thinking_level' => 'low',
            'timeout' => (int) config('ai.long_timeout'),
            'retries' => 1,
            'answer_style' => 'Tulis sebagai dokumen teks polos siap cetak. JANGAN memakai Markdown '
                .'(tanpa **tebal**, tanpa heading #, tanpa tabel pipa selain yang diminta format).',
        ], [
            'type' => $data['tool'],
            'type_label' => $toolLabel,
            'title' => $title,
            'metadata' => [
                'mapel' => $data['mapel'] ?? null,
                'jenjang' => $data['jenjang'] ?? null,
                'durasi' => $data['durasi'] ?? null,
                'file' => $request->file('file')?->getClientOriginalName(),
            ],
        ]);
    }

    /**
     * POST /ai/teacher/learning/preview - render hasil jadi dokumen RPM berformat tabel.
     * Memakai partial yang sama dengan export PDF, jadi yang dilihat guru = yang tercetak.
     * Murni parsing lokal (tanpa panggil AI), maka tak kena rate limit/audit.
     */
    public function previewLearning(Request $request): JsonResponse
    {
        $data = $this->validatedLearningExport($request);
        $doc = LearningDocument::parse($data['content']);

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
        $doc = LearningDocument::parse($data['content']);
        $written = $doc['parsed']
            ? LearningDocxBuilder::write($path, $doc)
            : $this->writeDocx($path, $title, $doc['text'], false, false);

        if (! $written) {
            abort(500, 'Gagal membuat file Word.');
        }

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /** POST /ai/teacher/learning/export-pdf - export hasil RPM Learning ke PDF. */
    public function exportLearningPdf(Request $request)
    {
        $data = $this->validatedLearningExport($request);
        $title = $this->learningExportTitle($data);
        $fileName = ($this->safeFileBase($title) ?: 'perangkat-ajar-learning').'-'.now()->format('Ymd-His').'.pdf';

        $doc = LearningDocument::parse($data['content']);

        $pdf = Pdf::loadView('ai.teacher-document-pdf', [
            'title' => $title,
            'content' => $doc['text'],
            'doc' => $doc,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($fileName);
    }

    /** POST /ai/teacher/summary - perangkum materi. */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'materi' => ['required', 'string', 'max:'.config('ai.max_input_chars')],
        ]);

        $prompt = "Rangkum materi berikut menjadi poin-poin ringkas untuk siswa:\n\n".$data['materi'];

        return $this->respond($request, 'teacher_summary', config('ai.teacher.summary'), $prompt, 2048, [], [
            'type' => 'summary',
            'type_label' => 'Perangkum Materi',
            'title' => Str::limit($data['materi'], 90),
            'metadata' => [
                'panjang_materi' => mb_strlen($data['materi']),
            ],
        ]);
    }

    /** POST /ai/teacher/feedback - draft komentar/feedback siswa. */
    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'konteks' => ['required', 'string', 'max:'.config('ai.max_input_chars')],
            'nama' => ['nullable', 'string', 'max:100'],
        ]);

        $nama = $data['nama'] ? "untuk siswa bernama {$data['nama']}" : '';
        $prompt = "Susun draf umpan balik {$nama} berdasarkan konteks berikut:\n\n".$data['konteks'];

        return $this->respond($request, 'teacher_feedback', config('ai.teacher.feedback'), $prompt, 2048, [], [
            'type' => 'feedback',
            'type_label' => 'Draft Feedback',
            'title' => ! empty($data['nama']) ? 'Feedback untuk '.$data['nama'] : Str::limit($data['konteks'], 90),
            'metadata' => [
                'nama' => $data['nama'] ?? null,
            ],
        ]);
    }

    /** Pipeline bersama: rate limit -> Gemini -> audit -> JSON. */
    private function respond(Request $request, string $feature, string $system, string $prompt, int $maxOutputTokens = 2048, array $options = [], ?array $historyData = null): JsonResponse
    {
        $userId = $request->user()->uuid;

        if ($limited = $this->aiRateLimited($feature, $userId)) {
            return $limited;
        }

        try {
            $result = $this->gemini->generate($prompt, $options + [
                'system' => $system,
                'max_output_tokens' => $maxOutputTokens, // keluaran guru cenderung lebih panjang
            ]);
        } catch (RuntimeException $e) {
            $this->logAiUsage($userId, $feature, config('ai.model'), 0, 0, 'error');

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'quota' => $this->aiPublicQuotaUsage(),
            ], 502);
        }

        $this->logAiUsage(
            $userId,
            $feature,
            $result['model'],
            $result['prompt_tokens'],
            $result['completion_tokens'],
            'success',
        );

        $history = $historyData !== null
            ? $this->storeHistory($userId, $historyData, $result['text'])
            : null;

        return response()->json([
            'ok' => true,
            'answer' => $result['text'],
            'history' => $history,
            'quota' => $this->aiPublicQuotaUsage(),
        ]);
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

    private function quizFormatInstruction(int $jumlah, array $jenisSoal, string $tingkat, ?string $jenjang, string $topik): string
    {
        $kelas = trim((string) $jenjang) !== '' ? trim((string) $jenjang) : '[KELAS / SEMESTER]';
        $topikJudul = trim($topik) !== '' ? mb_strtoupper($topik) : '[TOPIK]';
        $tingkatLabel = Str::title($tingkat);
        $jenisLabel = $this->quizTypeSummary($jenisSoal);
        $sectionTemplates = $this->quizSectionTemplates($jenisSoal);
        $answerKeyTemplates = $this->quizAnswerKeyTemplates($jenisSoal);
        $typeRules = $this->quizTypeRules($jenisSoal);

        return <<<TXT
FORMAT WAJIB mengikuti contoh file soal-agama-buddha.docx. Tulis teks polos dengan urutan ini, tanpa Markdown:

YAYASAN BUMI MAITRI
SMP MAITREYAWIRA TANJUNGPINANG
TERAKREDITASI A
Jl. Prof. Ir. Sutami No. 38  Telp (0771) 4505723  Email smpmai.tpi@gmail.com
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
- Jika data mata pelajaran/kelas/semester tidak tersedia, gunakan placeholder jelas, jangan mengarang data sekolah selain kop contoh.
- Jangan menulis pengantar atau catatan di luar dokumen soal.
TXT;
    }

    private function learningFormatInstruction(string $tool): string
    {
        return <<<'TXT'
Ikuti format RPM resmi SMP Maitreyawira PERSIS (hasil export Word/PDF harus sama bentuknya). Tulis teks polos tanpa Markdown (#, **, ```), tanpa pembuka/penutup di luar dokumen.

KOP WAJIB (6 baris, salin persis bila sekolah Maitreyawira; jika sekolah lain ganti baris 1–6 sesuai data, jangan mengarang):
YAYASAN BUMI MAITRI
SMP MAITREYAWIRA TANJUNGPINANG
TERAKREDITASI A
Komp. Gedung Pendidikan dan Pelatihan Buddhis Bumi Maitreya
Jl. Prof. Ir. Sutami No. 38  Telp (0771) 4505723  Email smpmai.tpi@gmail.com
Website http://www.maitreyawira-tpi.sch.id/

JUDUL:
PERENCANAAN PEMBELAJARAN MENDALAM
"[JUDUL TOPIK HURUF KAPITAL DALAM TANDA KUTIP]"

IDENTITAS (satu label per baris, spasi sebelum titik dua):
SEKOLAH : [nama]
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
[Nama Kepala Sekolah] | [Nama Guru]
NIK. [nomor] | NIK. [nomor/titik-titik]

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

Isi harus sesuai topik/mapel/kelas yang diminta. Jangan mengarang identitas yang tidak diberikan — pakai placeholder [Nama Guru], [Nama Kepala Sekolah], NIK. ...................... bila belum ada.
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
            $paragraphs .= $this->wordParagraph('Dibuat dari AI Asisten SIMS pada '.now()->format('d/m/Y H:i'));
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

    private function extractQuizDocumentText(string $path, string $extension, bool $preserveNewlines = false): string
    {
        $extension = strtolower($extension);

        try {
            $text = match ($extension) {
                'pdf' => (new PdfParser)->parseFile($path)->getText(),
                'docx' => $this->extractDocxText($path),
                'doc' => $this->extractLegacyDocText($path),
                default => '',
            };
        } catch (\Throwable) {
            return '';
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', (string) $text);

        if ($preserveNewlines) {
            $text = preg_replace("/[ \t]+/u", ' ', (string) $text);
            $text = preg_replace("/\n{3,}/u", "\n\n", (string) $text);

            return trim((string) $text);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function extractDocxText(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return '';
        }

        $parts = ['word/document.xml'];
        $text = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^word/(header|footer|footnotes|endnotes)\d*\.xml$#', $name)) {
                $parts[] = $name;
            }
        }

        foreach (array_unique($parts) as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false) {
                continue;
            }

            $xml = preg_replace('/<w:(tab|br|cr)[^>]*\/>/i', ' ', $xml);
            $xml = preg_replace('/<\/w:t>\s*<w:t[^>]*>/i', ' ', $xml);
            $xml = preg_replace('/<\/w:p>/i', "\n", $xml);
            $text .= ' '.html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $zip->close();

        return $text;
    }

    private function extractLegacyDocText(string $path): string
    {
        $raw = (string) file_get_contents($path);
        if ($raw === '') {
            return '';
        }

        preg_match_all('/[\x20-\x7E]{3,}/', $raw, $matches);

        return implode(' ', $matches[0] ?? []);
    }
}
