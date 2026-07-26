<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| Dokumen sumber RAG (FASE 5). status: pending|partial|processed|failed.
|
| `partial` berarti sebagian chunk sudah ter-embed tapi kuota harian Gemini habis
| di tengah jalan. Dokumen tetap bisa dipakai untuk retrieval sambil menunggu
| sisanya diproses otomatis setelah kuota reset.
*/
class AiDocument extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PARTIAL   = 'partial';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Dokumen yang diunggah admin lewat menu Dokumen AI (Analisis AI). */
    public const SOURCE_ADMIN_UPLOAD = 'admin_upload';

    /** Materi/buku yang diunggah guru lewat Generator Soal (Asisten Guru). */
    public const SOURCE_TEACHER_MATERIAL = 'teacher_material';

    protected $fillable = [
        'user_uuid', 'title', 'file_path', 'source', 'status', 'chunk_count',
        'error', 'quota_retries',
    ];

    protected $casts = [
        'chunk_count' => 'integer',
        'quota_retries' => 'integer',
    ];

    /** Status yang isinya sudah bisa dipakai retrieval (penuh maupun sebagian). */
    public static function searchableStatuses(): array
    {
        return [self::STATUS_PROCESSED, self::STATUS_PARTIAL];
    }

    /** Masih menunggu sisa chunk di-embed setelah kuota harian reset. */
    public function isAwaitingQuota(): bool
    {
        return $this->status === self::STATUS_PARTIAL;
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiDocumentChunk::class, 'document_id', 'uuid');
    }
}
