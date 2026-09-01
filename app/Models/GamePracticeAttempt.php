<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GamePracticeAttempt extends Model
{
    use HasUuids;

    protected $table = 'game_practice_attempts';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'session_id', 'participant_id', 'total_score', 'correct_count',
        'status', 'started_at', 'submitted_at', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'total_score'   => 'integer',
            'correct_count' => 'integer',
            'duration_ms'   => 'integer',
            'started_at'    => 'datetime',
            'submitted_at'  => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(GamePracticeSession::class, 'session_id', 'uuid');
    }

    public function participant()
    {
        return $this->belongsTo(GamePracticeParticipant::class, 'participant_id', 'uuid');
    }

    public function answers()
    {
        return $this->hasMany(GamePracticeAnswer::class, 'attempt_id', 'uuid');
    }
}
