<?php

namespace App\Jobs;

use App\Models\AiDocument;
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
 */
class IngestAiDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public string $documentUuid,
        public string $mime = '',
    ) {}

    public function handle(RagService $rag): void
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

        $rag->ingest($doc, $abs, $this->mime !== '' ? $this->mime : null);
    }
}
