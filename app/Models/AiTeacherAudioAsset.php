<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTeacherAudioAsset extends Model
{
    use HasUuids;

    protected $table = 'ai_teacher_audio_assets';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_uuid', 'source_type', 'source_uuid', 'title', 'text_snapshot', 'text_hash',
        'language', 'voice', 'voice_gender', 'vibe', 'tempo_percent', 'style_prompt', 'model', 'status', 'disk', 'path', 'mime',
        'size_bytes', 'duration_ms', 'error_message',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'duration_ms' => 'integer', 'tempo_percent' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function links()
    {
        return $this->hasMany(AiTeacherAudioLink::class, 'audio_uuid', 'uuid');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && filled($this->path);
    }
}