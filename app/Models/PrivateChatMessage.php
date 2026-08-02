<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivateChatMessage extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'private_chat_messages';

    protected $fillable = [
        'conversation_id', 'seq', 'sender_id', 'sender_nama', 'body', 'deleted_at', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(PrivateChatConversation::class, 'conversation_id', 'uuid');
    }

    public function isDihapus(): bool
    {
        return $this->deleted_at !== null;
    }
}
