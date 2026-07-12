<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Models\AiTeacherHistory;
use App\Services\GeminiService;
use App\Support\LearningDocument;
use App\Support\LearningDocxBuilder;
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
            'quotaUsage' => $this->aiQuotaUsageFor($user),
            'canViewQuotaUsage' => $this->canViewAiQuotaUsage($user),
        ]);
    }

    /** POST /ai/teacher/quiz - generator soal/kuis. */
    public function quiz(Request $request): JsonResponse
    {
        $data = $request->validate([
            'topik' => ['nullable', 'required_without:file', 'string', 'max:500'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:20'],
            'jenis' => ['required', 'in:pg,esai,campuran'],
            'tingkat' => ['required', 'in:mudah,sedang,sulit'],
            'jenjang' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

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

        $jenis = [
            'pg' => 'pilihan ganda (opsi A-D)',
            'esai' => 'esai/uraian',
            'campuran' => 'campuran pilihan ganda dan esai',
        ][$data['jenis']];

        $topik = trim((string) ($data['topik'] ?? ''));
        $jenjang = ! empty($data['jenjang']) ? "untuk jenjang {$data['jenjang']}" : '';

        if ($documentText !== '') {
            $maxChars = (int) config('ai.max_input_chars');
            $material = mb_substr($documentText, 0, $maxChars);
            $topicLine = $topik !== '' ? "Fokus topik: \"{$topik}\".\n" : '';

            $prompt = "Buat {$data['jumlah']} soal {$jenis} dengan tingkat kesulitan "
                ."{$data['tingkat']} {$jenjang} berdasarkan materi dari file berikut.\n"
                .$topicLine
                ."MATERI FILE:\n{$material}\n\n"
                .'Sertakan kunci jawaban.';
        } else {
            $prompt = "Buat {$data['jumlah']} soal {$jenis} dengan tingkat kesulitan "
                ."{$data['tingkat']} tentang topik: \"{$topik}\" {$jenjang}. "
                .'Sertakan kunci jawaban.';
        }

        return $this->respond($request, 'teacher_quiz', config('ai.teacher.quiz'), $prompt, 2048, [], [
            'type' => 'quiz',
            'type_label' => 'Generator Soal',
            'title' => $topik !== '' ? $topik : 'Soal dari file '.$request->file('file')?->getClientOriginalName(),
            'metadata' => [
                'jumlah' => $data['jumlah'],
                'jenis' => $data['jenis'],
                'tingkat' => $data['tingkat'],
                'jenjang' => $data['jenjang'] ?? null,
                'file' => $request->file('file')?->getClientOriginalName(),
            ],
        ]);
    }

    /** POST /ai/teacher/quiz/export-word - export hasil soal yang sudah bisa diedit guru. */
    public function exportQuizWord(Request $request)
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:50000'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $title = trim((string) ($data['title'] ?? '')) ?: 'Soal dari Asisten Guru';
        $safeName = Str::slug($title) ?: 'soal-asisten-ai';
        $fileName = $safeName.'-'.now()->format('Ymd-His').'.docx';
        $path = tempnam(sys_get_temp_dir(), 'ai-quiz-word-');

        if (! $path || ! $this->writeDocx($path, $title, $data['content'])) {
            abort(500, 'Gagal membuat file Word.');
        }

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /** POST /ai/teacher/learning - generator RPM Learning. */
    public function learning(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tool' => ['required', 'in:rpp'],
            'topik' => ['required', 'string', 'max:500'],
            'mapel' => ['nullable', 'string', 'max:100'],
            'jenjang' => ['nullable', 'string', 'max:100'],
            'durasi' => ['nullable', 'string', 'max:100'],
        ]);

        $toolLabel = $this->learningToolLabel($data['tool']);
        $details = array_filter([
            ! empty($data['mapel']) ? "Mata pelajaran: {$data['mapel']}" : null,
            ! empty($data['jenjang']) ? "Jenjang/kelas: {$data['jenjang']}" : null,
            ! empty($data['durasi']) ? "Alokasi waktu: {$data['durasi']}" : null,
        ]);
        $detailText = $details ? implode("\n", $details)."\n" : '';

        $prompt = "Buat {$toolLabel} siap pakai untuk guru dengan topik: \"{$data['topik']}\".\n"
            .$detailText
            ."Gunakan Bahasa Indonesia baku, praktis, dan langsung bisa direview guru.\n\n"
            .$this->learningFormatInstruction($data['tool']);

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
            'title' => $data['topik'],
            'metadata' => [
                'mapel' => $data['mapel'] ?? null,
                'jenjang' => $data['jenjang'] ?? null,
                'durasi' => $data['durasi'] ?? null,
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
                'quota' => $this->aiQuotaUsageFor($request->user()),
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
            'quota' => $this->aiQuotaUsageFor($request->user()),
        ]);
    }

    /**
     * Admin/superadmin boleh melihat angka penggunaan Gemini; guru hanya melihat keterangan kuota.
     */
    private function canViewAiQuotaUsage(?object $user): bool
    {
        return in_array($user?->access, ['superadmin', 'admin'], true);
    }

    /** @return array<string,mixed> */
    private function aiQuotaUsageFor(?object $user): array
    {
        $quota = $this->aiFreeTierUsage();
        $remaining = $quota['total']['remaining'] ?? null;
        $limit = $quota['total']['limit'] ?? null;
        $remainingPercent = $remaining !== null && $limit
            ? max(0, min(100, (int) floor(($remaining / $limit) * 100)))
            : null;
        $remainingLabel = $remaining !== null
            ? number_format((int) $remaining, 0, ',', '.').' request tersisa'
            : 'Sisa kuota tidak diketahui';

        $quota['remaining'] = $remaining;
        $quota['remaining_percent'] = $remainingPercent;
        $quota['remaining_label'] = $remainingLabel;

        if ($this->canViewAiQuotaUsage($user)) {
            $quota['can_view_usage'] = true;

            return $quota;
        }

        return [
            'enabled' => $quota['enabled'] ?? true,
            'status' => $quota['status'] ?? 'ok',
            'reset_at' => $quota['reset_at'] ?? null,
            'reset_at_human' => $quota['reset_at_human'] ?? '-',
            'day_start' => $quota['day_start'] ?? null,
            'day_start_human' => $quota['day_start_human'] ?? null,
            'remaining' => $remaining,
            'remaining_percent' => $remainingPercent,
            'remaining_label' => $remainingLabel,
            'total' => null,
            'models' => [],
            'can_view_usage' => false,
        ];
    }
    private function storeHistory(string $userId, array $data, string $answer): array
    {
        $history = AiTeacherHistory::create([
            'user_uuid' => $userId,
            'type' => $data['type'],
            'type_label' => $data['type_label'],
            'title' => Str::limit(trim((string) $data['title']) ?: $data['type_label'], 180),
            'excerpt' => Str::limit($this->plainExcerpt($answer), 500),
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

    private function learningFormatInstruction(string $tool): string
    {
        $toolInstruction = 'Khusus RPM: kuatkan alur kegiatan awal, inti, penutup, asesmen awal/proses/akhir, refleksi guru-siswa, dan tindak lanjut.';

        return <<<'TXT'
Ikuti format dokumen RPM contoh yang diberikan pengguna. Tulis sebagai dokumen polos siap export, tanpa Markdown heading "#", tanpa pembuka basa-basi, dan tanpa catatan tambahan di luar dokumen.

URUTAN FORMAT WAJIB:
1. Kop sekolah di bagian atas:
   YAYASAN / NAMA SEKOLAH / AKREDITASI / ALAMAT / KONTAK / WEBSITE.
   Jika data sekolah tidak diberikan, pakai placeholder yang jelas seperti [NAMA SEKOLAH], bukan mengarang.
2. Judul utama tengah:
   PERENCANAAN PEMBELAJARAN MENDALAM
   "[JUDUL TOPIK DALAM HURUF KAPITAL]"
3. Blok identitas:
   SEKOLAH, NAMA GURU, MATA PELAJARAN, KELAS / SEMESTER, ALOKASI WAKTU.
4. IDENTIFIKASI:
   Murid, Materi, Dimensi Profil Lulusan (DPL) dengan daftar DPL 1 sampai DPL 8 memakai tanda centang/kosong.
5. DESAIN PEMBELAJARAN:
   Capaian Pembelajaran, Lintas Disiplin Ilmu, Tujuan Pembelajaran, Topik Pembelajaran, Praktik Pedagogis, Kemitraan Pembelajaran, Lingkungan Pembelajaran, Pemanfaatan Digital.
6. PENGALAMAN BELAJAR:
   AWAL, INTI, MEMAHAMI, MENGAPLIKASI, MEREFLEKSI, PENUTUP.
   Setiap tahap harus menunjukkan 4 Pilar PM: Berkesadaran, Bermakna, Menggembirakan, dan Bernalar/Kritis-Kolaboratif sesuai konteks.
7. ASESMEN PEMBELAJARAN:
   Asesmen pada Awal Pembelajaran, Asesmen pada Proses Pembelajaran, Asesmen pada Akhir Pembelajaran.
8. Tanda tangan:
   Tempat/tanggal, Mengetahui Kepala Sekolah, Guru Mata Pelajaran, nama dan NIK/NIP placeholder bila belum ada.
9. LAMPIRAN:
   LAMPIRAN 1: ASESMEN AWAL PEMBELAJARAN.
   LAMPIRAN 2: ASESMEN PADA PROSES PEMBELAJARAN.
   LAMPIRAN 3: ASESMEN PADA AKHIR PEMBELAJARAN.
   Sertakan soal, rubrik/observasi, kisi-kisi, dan keterangan level Baru Mulai/Berkembang/Cakap/Mahir bila relevan.

ATURAN PENULISAN WAJIB (dokumen dikonversi otomatis ke tabel Word/PDF, ikuti persis):
- Nama sub-bagian ditulis di awal baris tersendiri diakhiri titik dua, contoh: "Murid:", "Capaian Pembelajaran:", "Asesmen pada Awal Pembelajaran:".
- Blok identitas ditulis "LABEL : nilai" per baris, contoh: "SEKOLAH : SMP Contoh".
- Daftar DPL ditulis 8 baris, masing-masing diawali "â˜‘ " (dicapai) atau "â˜ " (tidak), contoh: "â˜‘ DPL 1 Keimanan dan ketakwaan terhadap Tuhan Yang Maha Esa".
- Judul tahap PENGALAMAN BELAJAR ditulis kapital diikuti pilar dalam kurung pada baris yang sama, contoh: "AWAL (Berkesadaran, Bermakna, dan Menggembirakan)".
- Setiap butir kegiatan diawali "âœ“ ". Pertanyaan pemantik/refleksi ditulis dalam tanda kutip pada baris tersendiri.
- Blok tanda tangan: baris "[Tempat], [tanggal]" lalu baris-baris dua kolom dipisah " | ", contoh:
  Mengetahui, | Guru Mata Pelajaran
  Kepala Sekolah |
  [Nama Kepala Sekolah] | [Nama Guru]
  NIK. .......... | NIK. ..........
- Rubrik pada LAMPIRAN 2 ditulis sebagai tabel dengan pemisah " | ", baris pertama header:
  Kompetensi | Baru Mulai | Berkembang | Cakap | Mahir

Pastikan komponen wajib tetap terlihat di dalam format: Identitas Pembelajaran, DPL, Tujuan Pembelajaran, 4 Pilar PM, Kegiatan Pembelajaran, Asesmen, Refleksi, Lampiran dan Sumber Belajar.
TXT
            ."\n\n".$toolInstruction;
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
            $paragraphs .= $this->wordParagraph('Dibuat dari Asisten Guru SIMSku pada '.now()->format('d/m/Y H:i'));
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

    private function extractQuizDocumentText(string $path, string $extension): string
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
