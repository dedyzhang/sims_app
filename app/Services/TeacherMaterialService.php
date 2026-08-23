<?php

namespace App\Services;

use App\Exceptions\AiRateLimitedException;
use App\Models\AiDocument;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Support\DocumentText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/*
| Materi/buku guru untuk Generator Soal (RAG).
| Controller hanya map hasil ini ke HTTP — tidak ada logika pipeline di controller.
*/
class TeacherMaterialService
{
    public function __construct(
        private RagService $rag,
        private AiDocumentRegistrar $registrar,
        private GeminiService $gemini,
    ) {}

    /**
     * @return list<array{uuid:string,title:string,status:string,status_label:string,chunk_count:int,ready:bool,awaiting_quota:bool,error:?string,updated_at:?string}>
     */
    public function listPayloads(string $ownerUuid): array
    {
        return AiDocument::query()
            ->where('user_uuid', $ownerUuid)
            ->where('source', AiDocument::SOURCE_TEACHER_MATERIAL)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (AiDocument $doc) => $this->payload($doc))
            ->values()
            ->all();
    }

    /** @return array{uuid:string,title:string,status:string,status_label:string,chunk_count:int,ready:bool,awaiting_quota:bool,error:?string,updated_at:?string} */
    public function payload(AiDocument $doc): array
    {
        return [
            'uuid' => $doc->uuid,
            'title' => $doc->title,
            'status' => $doc->status,
            'status_label' => match ($doc->status) {
                AiDocument::STATUS_PENDING => 'Sedang diproses',
                AiDocument::STATUS_PARTIAL => 'Sebagian siap — menunggu kuota, lanjut otomatis',
                AiDocument::STATUS_PROCESSED => 'Siap dipakai',
                AiDocument::STATUS_FAILED => 'Gagal diproses',
                default => (string) $doc->status,
            },
            'chunk_count' => (int) ($doc->chunk_count ?? 0),
            'ready' => in_array($doc->status, AiDocument::searchableStatuses(), true),
            'awaiting_quota' => $doc->isAwaitingQuota(),
            'error' => $doc->error,
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Putuskan jalur unggahan: teks pendek inline, atau daftar dokumen RAG.
     *
     * @return array{0:string,1:?AiDocument}  [materi inline, dokumen RAG|null]
     *
     * @throws TeacherMaterialException
     */
    public function resolveUpload(User $owner, UploadedFile $file): array
    {
        $extension = (string) $file->getClientOriginalExtension();
        $text = DocumentText::extract($file->getRealPath(), $extension);
        $extractedByAi = false;

        if ($text === '' && strtolower($extension) === 'pdf') {
            $text = $this->extractPdfWithAiStudio($owner, $file);
            $extractedByAi = $text !== '';
        }

        if ($text === '') {
            throw TeacherMaterialException::extractFailed();
        }

        // File yang muat di budget prompt: tanpa embedding (hemat kuota sekolah).
        if (mb_strlen($text) <= (int) config('ai.max_input_chars')) {
            return [$text, null];
        }

        $document = $extractedByAi
            ? $this->registrar->registerExtractedText(
                $owner->uuid,
                $text,
                AiDocument::SOURCE_TEACHER_MATERIAL,
                $file->getClientOriginalName(),
            )
            : $this->registrar->register(
                $owner->uuid,
                $file,
                AiDocument::SOURCE_TEACHER_MATERIAL,
                $file->getClientOriginalName(),
                (string) $file->getClientMimeType(),
            );

        return ['', $document];
    }

    private function extractPdfWithAiStudio(User $owner, UploadedFile $file): string
    {
        $userUuid = $owner->uuid;

        try {
            $result = $this->gemini->documentText([[
                'binary' => (string) file_get_contents($file->getRealPath()),
                'mime' => 'application/pdf',
                'name' => $file->getClientOriginalName(),
            ]], [
                'api_key' => $owner->plainGeminiApiKey(),
                'timeout' => (int) config('ai.long_timeout', 120),
                'max_output_tokens' => 8192,
                'temperature' => 0.1,
            ]);
        } catch (RuntimeException $e) {
            $this->logPdfExtractionUsage($userUuid, config('ai.model'), 0, 0, 'error');

            throw TeacherMaterialException::provider($e->getMessage());
        }

        $this->logPdfExtractionUsage(
            $userUuid,
            $result['model'] ?? config('ai.model'),
            (int) ($result['prompt_tokens'] ?? 0),
            (int) ($result['completion_tokens'] ?? 0),
            'success',
        );

        $text = trim((string) ($result['text'] ?? ''));

        return mb_strtoupper($text) === 'TIDAK_TERBACA' ? '' : $text;
    }

    private function logPdfExtractionUsage(?string $userUuid, ?string $model, int $promptTokens, int $completionTokens, string $status): void
    {
        try {
            AiUsageLog::create([
                'user_uuid' => $userUuid,
                'feature' => 'teacher_pdf_extract',
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'status' => $status,
            ]);
        } catch (\Throwable) {
            // Audit pemakaian AI tidak boleh menggagalkan generate soal.
        }
    }

    /**
     * @throws TeacherMaterialException
     */
    public function findOwned(User $owner, string $documentUuid): ?AiDocument
    {
        if ($documentUuid === '') {
            return null;
        }

        $document = AiDocument::query()
            ->where('uuid', $documentUuid)
            ->where('user_uuid', $owner->uuid)
            ->where('source', AiDocument::SOURCE_TEACHER_MATERIAL)
            ->first();

        if (! $document) {
            throw TeacherMaterialException::notFound('Materi tidak ditemukan atau bukan milik Anda.');
        }

        return $document;
    }

    /**
     * Ambil teks materi yang relevan untuk topik (retrieval), atau lempar bila belum siap.
     *
     * @throws TeacherMaterialException
     */
    public function retrieveForTopic(
        AiDocument $document,
        string $topik,
        string $ownerUuid,
        bool $processPending = true,
    ): string
    {
        if ($processPending) {
            $document = $this->processPendingDocumentIfPossible($document);
        } else {
            $document->refresh();
        }

        if (! in_array($document->status, AiDocument::searchableStatuses(), true)) {
            $message = match ($document->status) {
                AiDocument::STATUS_FAILED => 'Materi gagal diproses: '.($document->error ?: 'penyebab tidak diketahui.'),
                AiDocument::STATUS_PENDING => 'Materi sedang diproses (embedding). Tunggu sebentar lalu buat soal lagi — Anda tidak perlu mengunggah ulang.',
                default => 'Materi belum siap dipakai. Tunggu pemrosesan selesai.',
            };

            throw TeacherMaterialException::processing($message, $document);
        }

        try {
            // Embed query: key guru (pemilik) dulu, sekolah hanya bila kuota guru habis.
            $hits = $this->rag->search(
                $topik,
                (int) config('ai.rag.quiz_top_k', 12),
                $document->uuid,
                $ownerUuid,
                $this->rag->embedOptionsForDocument($document),
            );
        } catch (RuntimeException $e) {
            throw TeacherMaterialException::provider($e->getMessage());
        }

        if ($hits === []) {
            throw TeacherMaterialException::noHits(
                'Tidak ada bagian materi yang cocok dengan topik "'.$topik.'". '
                .'Coba topik yang memakai istilah seperti tertulis di buku.'
            );
        }

        $material = implode("\n\n---\n\n", array_column($hits, 'content'));

        return mb_substr($material, 0, (int) config('ai.rag.quiz_material_chars', 24_000));
    }

    private function processPendingDocumentIfPossible(AiDocument $document): AiDocument
    {
        $document->refresh();

        if ($document->status !== AiDocument::STATUS_PENDING || ! $document->file_path) {
            return $document;
        }

        if (! Storage::disk('local')->exists($document->file_path)) {
            return $document;
        }

        try {
            $this->rag->ingest(
                $document,
                Storage::disk('local')->path($document->file_path),
                null,
                $this->rag->embedOptionsForDocument($document),
            );
        } catch (AiRateLimitedException) {
            // Biarkan UI tetap pada status processing dan mencoba lagi otomatis.
        }

        return $document->refresh();
    }

    /**
     * Batalkan pemrosesan materi dan bersihkan dari database.
     *
     * @throws TeacherMaterialException
     */
    public function cancelMaterial(string $documentUuid, User $owner): bool
    {
        $document = $this->findOwned($owner, $documentUuid);
        if ($document) {
            $this->rag->cancel($document);
            if ($document->file_path) {
                Storage::disk('local')->delete($document->file_path);
            }
            $document->delete();
        }

        return true;
    }
}
