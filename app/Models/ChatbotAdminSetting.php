<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotAdminSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_user_id',
        'notif_enabled',
        'sound_enabled',
        'message_notif_enabled',
    ];

    protected function casts(): array
    {
        return [
            'notif_enabled' => 'boolean',
            'sound_enabled' => 'boolean',
            'message_notif_enabled' => 'boolean',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
