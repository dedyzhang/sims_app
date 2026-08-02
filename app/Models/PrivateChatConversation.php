<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivateChatConversation extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'private_chat_conversations';

    protected $fillable = [
        'participant_one_id', 'participant_two_id', 'last_seq',
        'last_message_at', 'last_message_preview', 'one_read_seq', 'two_read_seq',
    ];

    protected function casts(): array
    {
        return [
            'last_seq' => 'integer',
            'one_read_seq' => 'integer',
            'two_read_seq' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function messages()
    {
        return $this->hasMany(PrivateChatMessage::class, 'conversation_id', 'uuid');
    }

    public function participantOne()
    {
        return $this->belongsTo(User::class, 'participant_one_id', 'uuid');
    }

    public function participantTwo()
    {
        return $this->belongsTo(User::class, 'participant_two_id', 'uuid');
    }

    public function includes(string $userId): bool
    {
        return $this->participant_one_id === $userId || $this->participant_two_id === $userId;
    }

    public function otherParticipant(string $userId): ?User
    {
        if ($this->participant_one_id === $userId) {
            return $this->participantTwo;
        }

        if ($this->participant_two_id === $userId) {
            return $this->participantOne;
        }

        return null;
    }

    public function readSeqFor(string $userId): int
    {
        return $this->participant_one_id === $userId
            ? (int) $this->one_read_seq
            : (int) $this->two_read_seq;
    }
}
