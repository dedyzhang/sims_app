<?php

namespace App\Services;

use App\Models\AiDocument;
use RuntimeException;

/**
 * Hasil domain materi guru yang gagal — dipetakan ke JSON di controller, bukan di service.
 */
class TeacherMaterialException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly bool $processing = false,
        public readonly ?AiDocument $document = null,
        public readonly bool $providerError = false,
    ) {
        parent::__construct($message);
    }

    public static function extractFailed(string $message): self
    {
        return new self($message, 422);
    }

    public static function notFound(string $message): self
    {
        return new self($message, 404);
    }

    public static function processing(string $message, AiDocument $document): self
    {
        return new self($message, 422, true, $document);
    }

    public static function noHits(string $message): self
    {
        return new self($message, 422);
    }

    public static function provider(string $message): self
    {
        return new self($message, 502, false, null, true);
    }

    /** @return array{ok:false,message:string,processing?:bool,document_uuid?:string,status?:string} */
    public function toArray(): array
    {
        $payload = [
            'ok' => false,
            'message' => $this->getMessage(),
        ];

        if ($this->processing && $this->document) {
            $payload['processing'] = true;
            $payload['document_uuid'] = $this->document->uuid;
            $payload['status'] = $this->document->status;
        }

        return $payload;
    }
}
