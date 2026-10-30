<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Models\AiTeacherHistory;
use App\Models\Pelajaran;
use App\Models\RpmDigital;
use App\Services\GeminiService;
use App\Support\DocumentText;
use App\Support\LearningDocument;
use App\Support\LearningDocxBuilder;
use App\Support\SchoolLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * Controller untuk modul RPM Digital (Rencana Pembelajaran Mendalam).
 * Mengelola pembuatan (AI langsung, upload PDF pelajaran dengan auto-delete,
 * atau import dari Asisten Guru), pratinjau, penyimpanan, edit, cetak,
 * dan export PDF/Word berformat resmi.
 */
class RpmDigitalController extends Controller
{
    use InteractsWithAi;

    public function __construct(
        private GeminiService $gemini
    ) {}

    /**
     * Tampilkan daftar dokumen RPM Digital tersimpan.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', RpmDigital::class);

        $user = $request->user();
        $isManager = $user->isAdmin() || in_array($user->access, ['kepala', 'kurikulum'], true);

        $query = RpmDigital::with('user');

        // Jika bukan manajer / kepala / kurikulum, hanya tampilkan dokumen miliknya sendiri
        if (! $isManager || $request->input('scope') === 'mine') {
            $query->where('user_uuid', $user->uuid);
        }

        if ($search = $request->input('search')) {
            $query->search($search);
        }

        if ($mapel = $request->input('mapel')) {
            $query->where('mapel', $mapel);
        }

        if ($kelas = $request->input('kelas')) {
            $query->where('kelas', $kelas);
        }

        if ($sourceType = $request->input('source_type')) {
            $query->where('source_type', $sourceType);
        }

        $rpms = $query->latest()->paginate(12)->withQueryString();

        // Statistik ringkas
        $totalCount = RpmDigital::count();
        $myCount = RpmDigital::where('user_uuid', $user->uuid)->count();
        $aiCount = RpmDigital::where('source_type', 'ai_direct')->count();
        $pdfCount = RpmDigital::where('source_type', 'pdf_upload')->count();
        $historyCount = RpmDigital::where('source_type', 'asisten_guru')->count();

        $mapelOptions = RpmDigital::distinct()->pluck('mapel')->filter()->values();

        return view('rpm.index', [
            'rpms' => $rpms,
            'isManager' => $isManager,
            'stats' => [
                'total' => $totalCount,
                'mine' => $myCount,
                'ai' => $aiCount,
                'pdf' => $pdfCount,
                'history' => $historyCount,
            ],
            'mapelOptions' => $mapelOptions,
        ]);
    }

    /**
     * Halaman form pembuatan RPM Digital baru (3 Tab: AI Langsung, Upload PDF, Import Asisten Guru).
     */
    public function create(Request $request): View
    {
        $this->authorize('create', RpmDigital::class);

        $user = $request->user();
        $pelajarans = Pelajaran::orderBy('urutan')->get(['uuid', 'nama', 'kode']);

        // Riwayat RPM yang sudah digenerate di Asisten Guru milik user yang login
        $aiTeacherHistories = AiTeacherHistory::where('user_uuid', $user->uuid)
            ->where(function ($q) {
                $q->whereIn('type', ['rpp', 'learning'])
                    ->orWhere('title', 'like', '%RPM%')
                    ->orWhere('type_label', 'like', '%RPM%');
            })
            ->latest()
            ->limit(50)
            ->get();

        return view('rpm.create', [
            'pelajarans' => $pelajarans,
            'aiTeacherHistories' => $aiTeacherHistories,
            'selectedTab' => $request->input('tab', 'ai_direct'),
            'selectedHistoryUuid' => $request->input('history_uuid'),
        ]);
    }

    /**
     * AJAX Generate RPM murni dari AI langsung.
     */
    public function generate(Request $request): JsonResponse
    {
        $this->authorize('create', RpmDigital::class);

        $request->validate([
            'topik' => ['required', 'string', 'max:500'],
            'mapel' => ['required', 'string', 'max:120'],
            'kelas' => ['nullable', 'string', 'max:50'],
            'semester' => ['nullable', 'string', 'max:50'],
            'alokasi_waktu' => ['nullable', 'string', 'max:100'],
            'detail' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $action = 'rpm_digital_generate';

        if ($limited = $this->aiRateLimited($action, $user->uuid)) {
            return $limited;
        }

        $topik = trim((string) $request->input('topik'));
        $mapel = trim((string) $request->input('mapel'));
        $kelas = trim((string) $request->input('kelas', 'VII'));
        $semester = trim((string) $request->input('semester', '1 (Ganjil)'));
        $alokasiWaktu = trim((string) $request->input('alokasi_waktu', '2 JP (2 x 40 Menit)'));
        $detail = trim((string) $request->input('detail', ''));

        $prompt = "Buat Perencanaan Pembelajaran Mendalam (RPM) siap pakai untuk guru dengan rincian:\n"
            ."- Topik Pembelajaran: \"{$topik}\"\n"
            ."- Mata Pelajaran: {$mapel}\n"
            ."- Kelas / Semester: {$kelas} / {$semester}\n"
            ."- Alokasi Waktu: {$alokasiWaktu}\n";

        if ($detail !== '') {
            $prompt .= "- Catatan / Fokus Tambahan dari Guru: {$detail}\n";
        }

        $prompt .= "\n\n".LearningDocument::formatInstruction('rpp');

        $system = (string) config('ai.teacher.learning', 'Anda adalah pakar kurikulum dan asisten perancang pembelajaran mendalam resmi sekolah.');

        @set_time_limit(180);

        try {
            $result = $this->gemini->generate($prompt, [
                'system' => $system,
                'max_output_tokens' => 4096,
                'api_key' => $user->plainGeminiApiKey(),
                'thinking_level' => 'low',
                'timeout' => 90,
            ]);
        } catch (RuntimeException $e) {
            $this->logAiUsage($user->uuid, $action, config('ai.model'), 0, 0, 'error');

            return response()->json([
                'ok' => false,
                'message' => 'Gagal menghasilkan RPM dari AI: '.$e->getMessage(),
            ], 502);
        }

        $this->logAiGenerationUsage($user->uuid, $action, $result, 'success');

        $answer = SchoolLetterhead::ensurePrefix($result['text']);
        $parsed = LearningDocument::parse($answer);

        return response()->json([
            'ok' => true,
            'answer' => $answer,
            'parsed' => $parsed['parsed'],
            'html' => view('ai.teacher-document-preview', [
                'doc' => $parsed,
                'content' => $parsed['text'],
            ])->render(),
            'title' => 'RPM: '.$topik,
            'mapel' => $mapel,
            'kelas' => $kelas,
            'semester' => $semester,
            'alokasi_waktu' => $alokasiWaktu,
            'topik' => $topik,
            'source_type' => 'ai_direct',
        ]);
    }

    /**
     * AJAX Generate RPM dari file PDF pelajaran.
     * Menerima file PDF materi (mendukung minimal 10MB hingga 50MB),
     * mengekstrak teks, generate RPM via AI, lalu MENGHAPUS file PDF di server.
     */
    public function generateFromPdf(Request $request): JsonResponse
    {
        $this->authorize('create', RpmDigital::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'], // s.d. 50MB
            'topik' => ['nullable', 'string', 'max:500'],
            'mapel' => ['nullable', 'string', 'max:120'],
            'kelas' => ['nullable', 'string', 'max:50'],
            'semester' => ['nullable', 'string', 'max:50'],
            'alokasi_waktu' => ['nullable', 'string', 'max:100'],
            'detail' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $action = 'rpm_digital_generate_pdf';

        if ($limited = $this->aiRateLimited($action, $user->uuid)) {
            return $limited;
        }

        $uploadedFile = $request->file('file');
        $originalFileName = $uploadedFile->getClientOriginalName();
        $tempPath = $uploadedFile->getRealPath();

        try {
            // 1. Ekstraksi teks dari file PDF
            $extractedText = DocumentText::extract($tempPath, 'pdf', true);

            if (mb_strlen(trim($extractedText)) < 30) {
                return response()->json([
                    'ok' => false,
                    'message' => 'PDF tidak memuat teks digital yang dapat dibaca (kemungkinan merupakan hasil scan gambar tanpa OCR). Gunakan file modul/buku teks PDF dengan teks digital.',
                ], 422);
            }

            // Batasi panjang teks konteks agar aman di context window (maksimal 25.000 karakter materi kunci)
            $maxChars = 25000;
            if (mb_strlen($extractedText) > $maxChars) {
                $materialSnippet = mb_substr($extractedText, 0, $maxChars)."\n... [teks materi berlanjut]";
            } else {
                $materialSnippet = $extractedText;
            }

            $topik = trim((string) $request->input('topik'));
            $mapel = trim((string) $request->input('mapel', ''));
            $kelas = trim((string) $request->input('kelas', 'VII'));
            $semester = trim((string) $request->input('semester', '1 (Ganjil)'));
            $alokasiWaktu = trim((string) $request->input('alokasi_waktu', '2 JP (2 x 40 Menit)'));
            $detail = trim((string) $request->input('detail', ''));

            $prompt = "Buat Perencanaan Pembelajaran Mendalam (RPM) siap pakai untuk guru berdasarkan materi/buku pelajaran yang diunggah berikut ini:\n\n";

            if ($topik !== '') {
                $prompt .= "- Fokus Topik Pembelajaran: \"{$topik}\"\n";
            } else {
                $prompt .= "- Fokus Topik Pembelajaran: Identifikasi materi pokok paling esensial dari teks pelajaran ini dan jadikan topik RPM.\n";
            }

            if ($mapel !== '') {
                $prompt .= "- Mata Pelajaran: {$mapel}\n";
            }
            $prompt .= "- Kelas / Semester: {$kelas} / {$semester}\n";
            $prompt .= "- Alokasi Waktu: {$alokasiWaktu}\n";

            if ($detail !== '') {
                $prompt .= "- Catatan Guru: {$detail}\n";
            }

            $prompt .= "\n--- ISI MATERI DARI BUKU/MODUL PELAJARAN (FILE: {$originalFileName}) ---\n";
            $prompt .= $materialSnippet."\n\n";
            $prompt .= LearningDocument::formatInstruction('rpp');

            $system = (string) config('ai.teacher.learning', 'Anda adalah pakar kurikulum dan asisten perancang pembelajaran mendalam resmi sekolah.');

            @set_time_limit(180);

            try {
                $result = $this->gemini->generate($prompt, [
                    'system' => $system,
                    'max_output_tokens' => 4096,
                    'api_key' => $user->plainGeminiApiKey(),
                    'thinking_level' => 'low',
                    'timeout' => 90,
                ]);
            } catch (RuntimeException $e) {
                $this->logAiUsage($user->uuid, $action, config('ai.model'), 0, 0, 'error');

                return response()->json([
                    'ok' => false,
                    'message' => 'Gagal menghasilkan RPM dari AI: '.$e->getMessage(),
                ], 502);
            }

            $this->logAiGenerationUsage($user->uuid, $action, $result, 'success');

            $answer = SchoolLetterhead::ensurePrefix($result['text']);
            $parsed = LearningDocument::parse($answer);

            $derivedTitle = $topik !== '' ? 'RPM: '.$topik : 'RPM dari '.$originalFileName;

            return response()->json([
                'ok' => true,
                'answer' => $answer,
                'parsed' => $parsed['parsed'],
                'html' => view('ai.teacher-document-preview', [
                    'doc' => $parsed,
                    'content' => $parsed['text'],
                ])->render(),
                'title' => $derivedTitle,
                'mapel' => $mapel,
                'kelas' => $kelas,
                'semester' => $semester,
                'alokasi_waktu' => $alokasiWaktu,
                'topik' => $topik ?: 'Berdasarkan materi: '.$originalFileName,
                'source_type' => 'pdf_upload',
                'source_file_name' => $originalFileName,
                'file_deleted' => true, // Konfirmasi file fisik sudah dihapus
            ]);
        } finally {
            // SANGAT PENTING: File fisik PDF sementara langsung dihapus setelah proses selesai
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * AJAX Pratinjau render dokumen RPM secara real-time.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RpmDigital::class);

        $request->validate([
            'content' => ['required', 'string'],
        ]);

        $content = SchoolLetterhead::ensurePrefix($request->input('content'));
        $doc = LearningDocument::parse($content);

        return response()->json([
            'ok' => true,
            'parsed' => $doc['parsed'],
            'html' => view('ai.teacher-document-preview', [
                'doc' => $doc,
                'content' => $doc['text'],
            ])->render(),
        ]);
    }

    /**
     * Simpan RPM Digital baru ke database.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', RpmDigital::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'mapel' => ['nullable', 'string', 'max:120'],
            'kelas' => ['nullable', 'string', 'max:50'],
            'semester' => ['nullable', 'string', 'max:50'],
            'alokasi_waktu' => ['nullable', 'string', 'max:100'],
            'topik' => ['nullable', 'string'],
            'source_type' => ['required', 'in:ai_direct,pdf_upload,asisten_guru,manual'],
            'source_file_name' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:30'],
        ]);

        $user = $request->user();

        $rpm = DB::transaction(function () use ($data, $user) {
            return RpmDigital::create([
                'school_id' => $user->school_id ?? '00000000-0000-0000-0000-000000000001',
                'user_uuid' => $user->uuid,
                'title' => $data['title'],
                'mapel' => $data['mapel'],
                'kelas' => $data['kelas'],
                'semester' => $data['semester'],
                'alokasi_waktu' => $data['alokasi_waktu'],
                'topik' => $data['topik'],
                'source_type' => $data['source_type'],
                'source_file_name' => $data['source_file_name'] ?? null,
                'content' => SchoolLetterhead::ensurePrefix($data['content']),
                'status' => 'draft',
            ]);
        });

        return redirect()->route('rpm.show', $rpm)
            ->with('success', 'Dokumen RPM Digital berhasil disimpan.');
    }

    /**
     * Tampilkan detail dokumen RPM Digital (Format interaktif dengan 7 tab & pratinjau ekspor).
     */
    public function show(RpmDigital $rpm): View
    {
        $this->authorize('view', $rpm);

        $doc = $rpm->parsedDocument();
        $identity = $rpm->extractIdentity();
        $quizQuestions = $rpm->extractQuizQuestions();
        $interactiveState = $rpm->interactive_state ?? [];

        return view('rpm.show', [
            'rpm' => $rpm,
            'doc' => $doc,
            'identity' => $identity,
            'quizQuestions' => $quizQuestions,
            'interactiveState' => $interactiveState,
            'content' => $doc['text'],
        ]);
    }

    /**
     * AJAX simpan status interaktif (ceklis langkah, jawaban kuis, refleksi guru).
     */
    public function saveInteractiveState(Request $request, RpmDigital $rpm): JsonResponse
    {
        $this->authorize('update', $rpm);

        $state = $request->validate([
            'checked_steps' => ['nullable', 'array'],
            'quiz_answers' => ['nullable', 'array'],
            'reflection_text' => ['nullable', 'string', 'max:5000'],
            'followup_text' => ['nullable', 'string', 'max:5000'],
        ]);

        $currentState = $rpm->interactive_state ?? [];
        $merged = array_merge($currentState, $state);

        $rpm->update([
            'interactive_state' => $merged,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Status interaktif berhasil disimpan.',
            'state' => $merged,
        ]);
    }

    /**
     * Halaman edit RPM Digital.
     */
    public function edit(RpmDigital $rpm): View
    {
        $this->authorize('update', $rpm);

        $pelajarans = Pelajaran::orderBy('urutan')->get(['uuid', 'nama', 'kode']);
        $doc = $rpm->parsedDocument();

        return view('rpm.edit', [
            'rpm' => $rpm,
            'doc' => $doc,
            'pelajarans' => $pelajarans,
        ]);
    }

    /**
     * Simpan perubahan dokumen RPM Digital.
     */
    public function update(Request $request, RpmDigital $rpm): RedirectResponse
    {
        $this->authorize('update', $rpm);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'mapel' => ['nullable', 'string', 'max:120'],
            'kelas' => ['nullable', 'string', 'max:50'],
            'semester' => ['nullable', 'string', 'max:50'],
            'alokasi_waktu' => ['nullable', 'string', 'max:100'],
            'topik' => ['nullable', 'string'],
            'content' => ['required', 'string', 'min:30'],
        ]);

        DB::transaction(function () use ($data, $rpm) {
            $rpm->update([
                'title' => $data['title'],
                'mapel' => $data['mapel'],
                'kelas' => $data['kelas'],
                'semester' => $data['semester'],
                'alokasi_waktu' => $data['alokasi_waktu'],
                'topik' => $data['topik'],
                'content' => SchoolLetterhead::ensurePrefix($data['content']),
            ]);
        });

        return redirect()->route('rpm.show', $rpm)
            ->with('success', 'Perubahan dokumen RPM Digital berhasil disimpan.');
    }

    /**
     * Hapus dokumen RPM Digital.
     */
    public function destroy(RpmDigital $rpm): RedirectResponse
    {
        $this->authorize('delete', $rpm);

        DB::transaction(function () use ($rpm) {
            $rpm->delete();
        });

        return redirect()->route('rpm.index')
            ->with('success', 'Dokumen RPM Digital berhasil dihapus.');
    }

    /**
     * Export dokumen RPM ke PDF resmi via DomPDF.
     */
    public function exportPdf(RpmDigital $rpm)
    {
        $this->authorize('view', $rpm);

        $doc = $rpm->parsedDocument();
        $title = $rpm->title;
        $fileName = (Str::slug($title) ?: 'rpm-digital').'-'.now()->format('Ymd-His').'.pdf';

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
     * Export dokumen RPM ke Word (.docx) resmi dengan tabel terstruktur via LearningDocxBuilder.
     */
    public function exportWord(RpmDigital $rpm)
    {
        $this->authorize('view', $rpm);

        $doc = $rpm->parsedDocument();
        $title = $rpm->title;
        $fileName = (Str::slug($title) ?: 'rpm-digital').'-'.now()->format('Ymd-His').'.docx';
        $path = tempnam(sys_get_temp_dir(), 'rpm-docx-');

        if (! $path) {
            abort(500, 'Gagal membuat file Word sementara.');
        }

        $written = $doc['parsed']
            ? LearningDocxBuilder::write($path, $doc)
            : $this->writeSimpleDocx($path, $title, $doc['text']);

        if (! $written) {
            abort(500, 'Gagal menyusun dokumen Word.');
        }

        return $this->attachmentDownload(
            $path,
            $fileName,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
    }

    /**
     * Fallback penyusunan file Word sederhana bila dokumen tidak berformat tabel formal.
     */
    private function writeSimpleDocx(string $path, string $title, string $content): bool
    {
        $clean = str_replace(["\r\n", "\r"], "\n", $content);
        $escapedTitle = htmlspecialchars($title, ENT_XML1, 'UTF-8');
        $paragraphs = '';

        foreach (explode("\n", $clean) as $line) {
            $t = trim($line);
            if ($t === '') {
                $paragraphs .= '<w:p><w:pPr><w:spacing w:after="80"/></w:pPr></w:p>';
            } else {
                $escaped = htmlspecialchars($t, ENT_XML1, 'UTF-8');
                $paragraphs .= "<w:p><w:r><w:t xml:space=\"preserve\">{$escaped}</w:t></w:r></w:p>";
            }
        }

        $body = "<w:p><w:pPr><w:jc w:val=\"center\"/><w:spacing w:after=\"200\"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val=\"32\"/></w:rPr><w:t>{$escapedTitle}</w:t></w:r></w:p>{$paragraphs}";

        return \App\Support\LearningDocxBuilder::writeDocxPackage($path, \App\Support\LearningDocxBuilder::documentXmlFor($body));
    }

    /**
     * Unduhan file attachment yang ramah browser desktop, mobile, & Android WebView.
     */
    private function attachmentDownload(string $path, string $fileName, string $contentType)
    {
        return response()->download($path, $fileName, $this->attachmentHeaders($fileName, $contentType))
            ->deleteFileAfterSend(true);
    }

    /**
     * Header HTTP standar untuk unduhan attachment.
     *
     * @return array<string, string>
     */
    private function attachmentHeaders(string $fileName, string $contentType): array
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName) ?: 'dokumen.bin';
        $utf8 = rawurlencode($fileName);

        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$ascii.'"; filename*=UTF-8\'\''.$utf8,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Content-Transfer-Encoding' => 'binary',
        ];
    }
}
