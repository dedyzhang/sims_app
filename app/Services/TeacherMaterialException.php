<?php

namespace App\Services;

use App\Models\AiDocument;
use RuntimeException;

/**
 * Hasil domain materi guru yang gagal — dipetakan ke JSON di controller, bukan di service.
 */
class TeacherMaterialException extends RuntimeException
{
    public const CODE_EXTRACT_FAILED = 'material_extract_failed';

    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly bool $processing = false,
        public readonly ?AiDocument $document = null,
        public readonly bool $providerError = false,
        public readonly ?string $errorCode = null,
        public readonly bool $suggestCamera = false,
        public readonly ?string $hint = null,
    ) {
        parent::__construct($message);
    }

    public static function extractFailed(): self
    {
        return new self(
            message: 'Teks tidak dapat diekstrak dari file. File ini kemungkinan PDF hasil scan/foto (hanya gambar), bukan dokumen ber-teks.',
            httpStatus: 422,
            errorCode: self::CODE_EXTRACT_FAILED,
            suggestCamera: true,
            hint: 'Gunakan sumber Foto buku untuk memotret halaman (cocok untuk Hanzi/aksara asing). '
                .'Alternatif: konversi PDF ke Word atau PDF ber-teks (searchable) di luar SIMS, lalu unggah lagi.',
        );
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

    /** @return array{ok:false,message:string,processing?:bool,document_uuid?:string,status?:string,error_code?:string,suggest_camera?:bool,hint?:string} */
    public function toArray(): array
    {
        $payload = [
            'ok' => false,
            'message' => $this->getMessage(),
        ];

        if ($this->errorCode !== null) {
            $payload['error_code'] = $this->errorCode;
        }

        if ($this->suggestCamera) {
            $payload['suggest_camera'] = true;
        }

        if ($this->hint !== null && $this->hint !== '') {
            $payload['hint'] = $this->hint;
        }

        if ($this->processing && $this->document) {
            $payload['processing'] = true;
            $payload['document_uuid'] = $this->document->uuid;
            $payload['status'] = $this->document->status;
        }

        return $payload;
    }
}
