<?php

namespace App\Services;

use App\Jobs\IngestAiDocumentJob;
use App\Models\AiDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Titik masuk kanonik: simpan file + buat baris ai_documents + antre/jalankan ingest.
| Dipakai admin (Dokumen AI) dan guru (Generator Soal) supaya lifecycle status tidak drift.
*/
class AiDocumentRegistrar
{
    public function __construct(private RagService $rag) {}

    /**
     * @param  string  $source  AiDocument::SOURCE_* 
     */
    public function register(
        string $ownerUuid,
        UploadedFile $file,
        string $source,
        ?string $title = null,
        ?string $mime = null,
    ): AiDocument {
        $path = $file->store('ai_documents', 'local');
        $resolvedMime = $mime ?: (string) ($file->getClientMimeType() ?: $file->getMimeType());

        $doc = AiDocument::create([
            'user_uuid' => $ownerUuid,
            'title' => $title ?: $file->getClientOriginalName(),
            'file_path' => $path,
            'source' => $source,
            'status' => AiDocument::STATUS_PENDING,
        ]);

        $this->dispatchIngest($doc, $resolvedMime);

        return $doc->refresh();
    }

    /** Antre job atau ingest sinkron sesuai config. */
    public function dispatchIngest(AiDocument $doc, string $mime = ''): void
    {
        if (config('ai.rag.queue_ingest', true)) {
            IngestAiDocumentJob::dispatch($doc->uuid, $mime);

            return;
        }

        if (! $doc->file_path) {
            return;
        }

        $this->rag->ingest(
            $doc,
            Storage::disk('local')->path($doc->file_path),
            $mime !== '' ? $mime : null,
        );
    }
}
