<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| Dokumen sumber RAG (FASE 5). status: pending|processed|failed.
*/
class AiDocument extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'user_uuid', 'title', 'file_path', 'status', 'chunk_count', 'error',
    ];

    protected $casts = [
        'chunk_count' => 'integer',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(AiDocumentChunk::class, 'document_id', 'uuid');
    }
}
