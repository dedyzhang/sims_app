<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithAi;
use App\Services\GeminiService;
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
        return view('ai.teacher');
    }

    /** POST /ai/teacher/quiz - generator soal/kuis. */
    public function quiz(Request $request): JsonResponse
    {
        $data = $request->validate([
            'topik'   => ['nullable', 'required_without:file', 'string', 'max:500'],
            'jumlah'  => ['required', 'integer', 'min:1', 'max:20'],
            'jenis'   => ['required', 'in:pg,esai,campuran'],
            'tingkat' => ['required', 'in:mudah,sedang,sulit'],
            'jenjang' => ['nullable', 'string', 'max:100'],
            'file'    => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
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
            'pg'       => 'pilihan ganda (opsi A-D)',
            'esai'     => 'esai/uraian',
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

        return $this->respond($request, 'teacher_quiz', config('ai.teacher.quiz'), $prompt);
    }

    /** POST /ai/teacher/quiz/export-word - export hasil soal yang sudah bisa diedit guru. */
    public function exportQuizWord(Request $request)
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:50000'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $title = trim((string) ($data['title'] ?? '')) ?: 'Soal dari Asisten AI';
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
    /** POST /ai/teacher/summary - perangkum materi. */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'materi' => ['required', 'string', 'max:'.config('ai.max_input_chars')],
        ]);

        $prompt = "Rangkum materi berikut menjadi poin-poin ringkas untuk siswa:\n\n".$data['materi'];

        return $this->respond($request, 'teacher_summary', config('ai.teacher.summary'), $prompt);
    }

    /** POST /ai/teacher/feedback - draft komentar/feedback siswa. */
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

    /** Pipeline bersama: rate limit -> Gemini -> audit -> JSON. */
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

    private function writeDocx(string $path, string $title, string $content): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', $this->wordDocumentXml($title, $content));

        return $zip->close();
    }

    private function wordDocumentXml(string $title, string $content): string
    {
        $lines = preg_split('/\R/u', trim($content)) ?: [];
        $paragraphs = $this->wordParagraph($title, true);
        $paragraphs .= $this->wordParagraph('Dibuat dari Asisten AI SIMSku pada '.now()->format('d/m/Y H:i'));
        $paragraphs .= '<w:p/>';

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
                'pdf' => (new PdfParser())->parseFile($path)->getText(),
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
        $zip = new ZipArchive();
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
