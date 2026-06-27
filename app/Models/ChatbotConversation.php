<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Percakapan chatbot. Diintegrasikan ke SIMS: kunci pengguna memakai users.uuid
 * (string). Tidak memakai SchoolScope/school_id — SIMS satu sekolah.
 */
class ChatbotConversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'mode',
        'status',
        'assigned_admin_id',
        'started_at',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class, 'conversation_id');
    }

    public function isBotMode(): bool
    {
        return $this->mode === 'bot';
    }

    public function isHumanMode(): bool
    {
        return $this->mode === 'human';
    }
}
