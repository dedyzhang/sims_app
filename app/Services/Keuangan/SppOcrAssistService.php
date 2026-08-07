<?php

namespace App\Services\Keuangan;

use App\Models\SppOcrDraft;
use App\Models\SppPembayaran;
use App\Services\GeminiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * OCR asisten bukti transfer (A2) — saran HITL, bukan auto-post.
 */
class SppOcrAssistService
{
    public function __construct(private GeminiService $gemini) {}

    /**
     * Ekstrak saran dari gambar/PDF bukti. Hasil disimpan sebagai draft, tidak menulis nominal resmi.
     *
     * @return array{nama_pengirim:?string, tanggal:?string, referensi:?string, nominal_teks:?string, draft_uuid:string}
     */
    public function suggest(SppPembayaran $pembayaran, UploadedFile $file, ?string $actorUuid): array
    {
        $mime = $file->getMimeType() ?: 'image/jpeg';
        $binary = (string) file_get_contents($file->getRealPath());

        $saran = $this->extractFromImage($binary, $mime);

        $draft = SppOcrDraft::create([
            'pembayaran_uuid' => $pembayaran->uuid,
            'saran'           => $saran,
            'file_path'       => null,
            'dibuat_oleh'     => $actorUuid,
            'kadaluarsa_pada' => now()->addDays((int) config('keuangan-ai.ocr.ttl_hari', 30)),
        ]);

        return array_merge($saran, ['draft_uuid' => $draft->uuid]);
    }

    /**
     * @return array{nama_pengirim:?string, tanggal:?string, referensi:?string, nominal_teks:?string}
     */
    private function extractFromImage(string $binary, string $mime): array
    {
        $empty = ['nama_pengirim' => null, 'tanggal' => null, 'referensi' => null, 'nominal_teks' => null];

        if (! $this->gemini->enabled()) {
            return $empty;
        }

        try {
            $result = $this->gemini->visionText(
                [['binary' => $binary, 'mime' => $mime]],
                ['prompt' => config('keuangan-ai.ocr.prompt'), 'temperature' => 0.1, 'max_output_tokens' => 512],
            );

            $parsed = $this->parseJsonResponse($result['text'] ?? '');

            return [
                'nama_pengirim' => $parsed['nama_pengirim'] ?? null,
                'tanggal'       => $parsed['tanggal'] ?? null,
                'referensi'     => $parsed['referensi'] ?? null,
                'nominal_teks'  => $parsed['nominal_teks'] ?? null,
            ];
        } catch (RuntimeException) {
            return $empty;
        }
    }

    /** @return array<string, mixed> */
    private function parseJsonResponse(string $text): array
    {
        $text = trim($text);
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /** Hapus draft setelah verifikasi selesai. */
    public function purgeForPembayaran(SppPembayaran $pembayaran): void
    {
        SppOcrDraft::where('pembayaran_uuid', $pembayaran->uuid)->delete();
    }
}
