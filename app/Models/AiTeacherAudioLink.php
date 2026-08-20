<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTeacherAudioLink extends Model
{
    use HasUuids;

    protected $table = 'ai_teacher_audio_links';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['audio_uuid', 'target_type', 'target_uuid', 'created_by'];

    public function audio(): BelongsTo
    {
        return $this->belongsTo(AiTeacherAudioAsset::class, 'audio_uuid', 'uuid');
    }
}