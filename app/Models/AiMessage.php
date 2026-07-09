<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| Satu pesan dalam percakapan chatbot AI (FASE 2). role = user|assistant.
*/
class AiMessage extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'token_estimate',
    ];

    protected $casts = [
        'token_estimate' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id', 'uuid');
    }
}
