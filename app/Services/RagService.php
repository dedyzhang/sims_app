<?php

namespace App\Services;

use App\Models\AiDocument;
use App\Models\AiDocumentChunk;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;

/*
| Mesin RAG dokumen sekolah (FASE 5). Ekstrak teks (PDF/TXT) → chunk → embed
| (GeminiService) → simpan JSON. Pencarian: embed query → cosine manual di PHP
| (SQLite tanpa pgvector) → chunk termirip untuk konteks jawaban.
*/
class RagService
{
    public function __construct(private GeminiService $gemini) {}

    /** Ekstrak teks mentah dari file dokumen. */
    public function extractText(string $absPath, ?string $mime = null): string
    {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        if ($mime === 'application/pdf' || $ext === 'pdf') {
            $text = (new PdfParser())->parseFile($absPath)->getText();
        } else {
            $text = (string) file_get_contents($absPath);
        }

        // Normalkan spasi/karakter kontrol.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Potong teks jadi chunk berukuran ~chunk_chars dengan overlap, pecah di batas kata. */
    public function chunk(string $text): array
    {
        $size    = max(200, (int) config('ai.rag.chunk_chars'));
        $overlap = max(0, (int) config('ai.rag.chunk_overlap'));
        $maxN    = (int) config('ai.rag.max_chunks');
        $len     = mb_strlen($text);

        if ($len === 0) return [];

        $chunks = [];
        $start = 0;
        while ($start < $len) {
            $end   = min($start + $size, $len);
            $piece = mb_substr($text, $start, $end - $start);

            // Hindari memotong di tengah kata (kecuali chunk terakhir).
            if ($end < $len) {
                $lastSpace = mb_strrpos($piece, ' ');
                if ($lastSpace !== false && $lastSpace > $size * 0.6) {
                    $piece = mb_substr($piece, 0, $lastSpace);
                }
            }

            $trimmed = trim($piece);
            if ($trimmed !== '') {
                $chunks[] = $trimmed;
            }

            // Sudah mencapai akhir teks → selesai (cegah loop chunk mini berulang).
            if ($end >= $len) break;

            $advance = max(1, mb_strlen($piece) - $overlap);
            $start += $advance;

            if (count($chunks) >= $maxN) break;
        }

        return $chunks;
    }

    /**
     * Proses dokumen: ekstrak → chunk → embed → simpan. Set status processed|failed.
     * @return int Jumlah chunk yang tersimpan.
     */
    public function ingest(AiDocument $doc, string $absPath, ?string $mime = null): int
    {
        try {
            $text = $this->extractText($absPath, $mime);
            if ($text === '') {
                throw new RuntimeException('Teks tidak dapat diekstrak dari dokumen (mungkin PDF hasil scan/gambar).');
            }

            $pieces = $this->chunk($text);
            if ($pieces === []) {
                throw new RuntimeException('Dokumen kosong setelah diproses.');
            }

            $doc->chunks()->delete(); // re-ingest aman

            foreach ($pieces as $i => $piece) {
                $vec = $this->gemini->embed($piece);
                AiDocumentChunk::create([
                    'document_id' => $doc->uuid,
                    'ord'         => $i,
                    'content'     => $piece,
                    'embedding'   => $vec,
                ]);
            }

            $doc->update([
                'status'      => AiDocument::STATUS_PROCESSED,
                'chunk_count' => count($pieces),
                'error'       => null,
            ]);

            return count($pieces);
        } catch (\Throwable $e) {
            $doc->chunks()->delete();
            $doc->update([
                'status'      => AiDocument::STATUS_FAILED,
                'chunk_count' => 0,
                'error'       => mb_substr($e->getMessage(), 0, 500),
            ]);

            return 0;
        }
    }

    /**
     * Cari chunk paling mirip dengan query (cosine). Hanya dokumen processed.
     * @return array<int, array{content:string, title:string, score:float}>
     */
    public function search(string $query, ?int $k = null): array
    {
        $k = $k ?? (int) config('ai.rag.top_k');
        $qvec = $this->gemini->embed($query);

        $chunks = AiDocumentChunk::whereHas('document', function ($q) {
            $q->where('status', AiDocument::STATUS_PROCESSED);
        })->with('document:uuid,title')->get(['uuid', 'document_id', 'content', 'embedding']);

        $scored = [];
        foreach ($chunks as $ch) {
            if (empty($ch->embedding)) continue;
            $scored[] = [
                'content' => $ch->content,
                'title'   => $ch->document?->title ?? 'Dokumen',
                'score'   => $this->cosine($qvec, $ch->embedding),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $k);
    }

    /** Cosine similarity dua vektor. */
    public function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na == 0.0 || $nb == 0.0) return 0.0;

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
