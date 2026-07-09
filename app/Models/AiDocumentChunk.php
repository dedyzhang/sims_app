<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| Potongan teks dokumen + embedding JSON (FASE 5).
*/
class AiDocumentChunk extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'document_id', 'ord', 'content', 'embedding',
    ];

    protected $casts = [
        'ord'       => 'integer',
        'embedding' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(AiDocument::class, 'document_id', 'uuid');
    }
}
