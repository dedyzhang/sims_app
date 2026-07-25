<?php

namespace App\Jobs;

use App\Exceptions\AiRateLimitedException;
use App\Models\AiDocument;
use App\Services\GeminiService;
use App\Services\RagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Embed dokumen RAG di antrean agar upload HTTP tidak timeout.
 * Di testing (QUEUE_CONNECTION=sync) job tetap jalan sinkron.
 *
 * - Kuota HARIAN habis → status partial + jadwalkan setelah reset free tier.
 * - 429 per-menit → release singkat, jangan menunggu tengah malam.
 */
class IngestAiDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 300;

    public function __construct(
        public string $documentUuid,
        public string $mime = '',
    ) {}

    public function handle(RagService $rag, GeminiService $gemini): void
    {
        $doc = AiDocument::find($this->documentUuid);
        if (! $doc || ! $doc->file_path) {
            return;
        }

        $abs = Storage::disk('local')->path($doc->file_path);
        if (! is_file($abs)) {
            $doc->update([
                'status' => AiDocument::STATUS_FAILED,
                'chunk_count' => 0,
                'error' => 'File dokumen tidak ditemukan di penyimpanan.',
            ]);

            return;
        }

        try {
            // Materi guru: key pribadi pemilik → sekolah. Admin: key sekolah.
            $rag->ingest(
                $doc,
                $abs,
                $this->mime !== '' ? $this->mime : null,
                $rag->embedOptionsForDocument($doc),
            );
        } catch (AiRateLimitedException $e) {
            // Tunda singkat; jangan pakai jadwal midnight (itu khusus kuota harian).
            $this->release(max(15, $e->retryAfterSeconds));

            return;
        }

        $doc->refresh();
        if ($doc->status === AiDocument::STATUS_PARTIAL) {
            $this->scheduleDailyQuotaResume($doc, $gemini);
        }
    }

    /** Antre lagi setelah kuota harian Gemini reset, sampai batas percobaan. */
    private function scheduleDailyQuotaResume(AiDocument $doc, GeminiService $gemini): void
    {
        $maxRetries = max(1, (int) config('ai.rag.max_quota_retries', 7));

        if ($doc->quota_retries >= $maxRetries) {
            $doc->update([
                'error' => 'Ingest dihentikan setelah '.$maxRetries.' kali menunggu kuota harian. '
                    .'Bagian dokumen yang sudah diproses tetap bisa dipakai.',
            ]);

            return;
        }

        $doc->increment('quota_retries');

        $resumeAt = $gemini->nextFreeTierResetAt()->addMinutes(5);

        self::dispatch($this->documentUuid, $this->mime)->delay($resumeAt);
    }
}
