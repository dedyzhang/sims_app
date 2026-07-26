<?php

namespace App\Services;

use App\Exceptions\AiDailyQuotaExhaustedException;
use App\Exceptions\AiRateLimitedException;
use App\Models\AiDocument;
use App\Models\AiDocumentChunk;
use App\Models\User;
use App\Support\DocumentText;
use RuntimeException;

/*
| Mesin RAG dokumen sekolah (FASE 5). Ekstrak teks (PDF/TXT) → chunk → embed
| (GeminiService) → simpan JSON. Pencarian: embed query → cosine manual di PHP
| (SQLite tanpa pgvector) → chunk termirip untuk konteks jawaban.
*/
class RagService
{
    public function __construct(private GeminiService $gemini) {}

    /** Ekstrak teks mentah dari file dokumen (PDF, DOCX, DOC, teks polos). */
    public function extractText(string $absPath, ?string $mime = null): string
    {
        // Ekstensi file lebih dipercaya daripada MIME; MIME hanya jadi cadangan
        // saat file tersimpan tanpa ekstensi.
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = DocumentText::extensionFromMime($mime);
        }

        $text = DocumentText::extract($absPath, $ext);

        $maxChars = max(1000, (int) config('ai.rag.max_extract_chars', 200_000));
        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars);
        }

        return $text;
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
     * Proses dokumen: ekstrak → chunk → embed → simpan.
     *
     * Bisa dilanjutkan: chunk yang sudah ter-embed dilewati, sehingga ingest yang
     * terhenti karena kuota harian Gemini habis bisa disambung keesokan harinya
     * tanpa mengulang (dan tanpa membakar kuota untuk pekerjaan yang sama).
     *
     * Status akhir: processed (selesai) | partial (kuota habis, lanjut nanti)
     * | failed (gagal permanen).
     *
     * @param  array{api_key?:string}  $embedOptions  key guru (opsional); kuota habis → sekolah di GeminiService
     * @return int Total chunk dokumen ini yang sudah tersimpan.
     */
    public function ingest(AiDocument $doc, string $absPath, ?string $mime = null, array $embedOptions = []): int
    {
        if ($embedOptions === []) {
            $embedOptions = $this->embedOptionsForDocument($doc);
        }

        try {
            $text = $this->extractText($absPath, $mime);
            if ($text === '') {
                throw new RuntimeException('Teks tidak dapat diekstrak dari dokumen (mungkin PDF hasil scan/gambar).');
            }

            $pieces = $this->chunk($text);
            if ($pieces === []) {
                throw new RuntimeException('Dokumen kosong setelah diproses.');
            }
        } catch (\Throwable $e) {
            // Gagal di tahap ekstraksi/chunking = gagal permanen: dokumen tak terbaca.
            return $this->markFailed($doc, $e);
        }

        // Chunk yang sudah ter-embed pada percobaan sebelumnya. Isinya di-cek juga,
        // bukan cuma jumlahnya: bila file diganti, hasil chunk bisa bergeser dan
        // embedding lama tidak lagi mewakili teks di posisi itu.
        $existing = $doc->chunks()->pluck('content', 'ord')->all();

        $done = 0;
        foreach ($pieces as $i => $piece) {
            $freshStatus = AiDocument::query()->where('uuid', $doc->uuid)->value('status');
            if ($freshStatus === AiDocument::STATUS_CANCELLED) {
                $doc->chunks()->delete();
                return 0;
            }

            if (($existing[$i] ?? null) === $piece) {
                $done++;

                continue;
            }

            try {
                $vec = $this->gemini->embed($piece, $embedOptions);
            } catch (AiDailyQuotaExhaustedException $e) {
                // Kuota harian: simpan progres, job menjadwalkan resume setelah reset.
                return $this->markPartial($doc, $done, $e);
            } catch (AiRateLimitedException $e) {
                // RPM: simpan progres bila ada, biarkan job menunda singkat (bukan midnight).
                if ($done > 0) {
                    $this->markPartial($doc, $done, $e);
                }
                throw $e;
            } catch (\Throwable $e) {
                return $this->markFailed($doc, $e);
            }

            // Cek sekali lagi setelah HTTP request sebelum menyimpan ke database
            if (AiDocument::query()->where('uuid', $doc->uuid)->value('status') === AiDocument::STATUS_CANCELLED) {
                $doc->chunks()->delete();
                return 0;
            }

            // updateOrCreate, bukan create: posisi ord ini mungkin sudah terisi hasil
            // ingest sebelumnya dengan teks berbeda (file diganti).
            AiDocumentChunk::updateOrCreate(
                ['document_id' => $doc->uuid, 'ord' => $i],
                ['content' => $piece, 'embedding' => $vec],
            );
            $done++;
        }

        // Buang sisa chunk dari ingest lama yang lebih panjang dari dokumen sekarang.
        $doc->chunks()->where('ord', '>=', count($pieces))->delete();

        $doc->update([
            'status'        => AiDocument::STATUS_PROCESSED,
            'chunk_count'   => $done,
            'error'         => null,
            'quota_retries' => 0,
        ]);

        return $done;
    }

    /** Batalkan pemrosesan dokumen dan bersihkan chunk yang sudah dibuat. */
    public function cancel(AiDocument $doc): void
    {
        $doc->update([
            'status' => AiDocument::STATUS_CANCELLED,
            'error' => 'Pemrosesan dibatalkan oleh pengguna.',
        ]);
        $doc->chunks()->delete();
    }

    /** Kuota harian habis di tengah jalan: simpan progres, tandai untuk dilanjutkan. */
    private function markPartial(AiDocument $doc, int $done, \Throwable $e): int
    {
        $doc->update([
            'status'      => AiDocument::STATUS_PARTIAL,
            'chunk_count' => $done,
            'error'       => mb_substr($e->getMessage(), 0, 500),
        ]);

        return $done;
    }

    /** Gagal permanen: dokumen tak terbaca atau error non-kuota. Bersihkan chunk. */
    private function markFailed(AiDocument $doc, \Throwable $e): int
    {
        $doc->chunks()->delete();
        $doc->update([
            'status'      => AiDocument::STATUS_FAILED,
            'chunk_count' => 0,
            'error'       => mb_substr($e->getMessage(), 0, 500),
        ]);

        return 0;
    }

    /**
     * Cari chunk paling mirip dengan query (cosine).
     *
     * Dokumen `partial` ikut dicari: guru tetap bisa membuat soal dari bagian buku
     * yang sudah ter-embed sambil menunggu sisanya diproses setelah kuota reset.
     *
     * Kandidat dibatasi search_candidate_limit agar tidak O(n) seluruh korpus.
     *
     * @param  string|null  $ownerUuid  Batasi ke dokumen milik user ini (guru hanya
     *                                  mencari di materinya sendiri).
     * @param  array{api_key?:string}  $embedOptions  key guru dulu, sekolah cadangan
     * @return array<int, array{content:string, title:string, score:float}>
     */
    public function search(
        string $query,
        ?int $k = null,
        ?string $documentId = null,
        ?string $ownerUuid = null,
        array $embedOptions = [],
    ): array {
        $k = $k ?? (int) config('ai.rag.top_k');
        $candidateLimit = max($k, (int) config('ai.rag.search_candidate_limit', 400));
        $qvec = $this->gemini->embed($query, $embedOptions);

        $chunks = AiDocumentChunk::query()
            ->whereHas('document', function ($q) use ($documentId, $ownerUuid) {
                $q->whereIn('status', AiDocument::searchableStatuses());
                if ($documentId) {
                    $q->where('uuid', $documentId);
                }
                if ($ownerUuid) {
                    $q->where('user_uuid', $ownerUuid);
                }
            })
            ->with('document:uuid,title')
            ->orderByDesc('created_at')
            ->limit($candidateLimit)
            ->get(['uuid', 'document_id', 'content', 'embedding']);

        $scored = [];
        foreach ($chunks as $ch) {
            if (empty($ch->embedding)) {
                continue;
            }
            $scored[] = [
                'content' => $ch->content,
                'title' => $ch->document?->title ?? 'Dokumen',
                'score' => $this->cosine($qvec, $ch->embedding),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $k);
    }

    /**
     * Opsi embed untuk dokumen: materi guru memakai key pribadi pemilik dulu.
     * Dokumen admin → rantai default (key sekolah saja).
     *
     * @return array{api_key?:string}
     */
    public function embedOptionsForDocument(AiDocument $doc): array
    {
        if ($doc->source !== AiDocument::SOURCE_TEACHER_MATERIAL) {
            return [];
        }

        $user = User::query()->find($doc->user_uuid);
        $key = $user?->plainGeminiApiKey();

        return $key ? ['api_key' => $key] : [];
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
