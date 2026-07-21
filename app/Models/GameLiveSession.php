<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GameLiveSession extends Model
{
    use HasUuids;

    protected $table = 'game_live_sessions';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'quiz_id', 'classroom_id', 'hosted_by', 'status',
        'current_question_id', 'question_index',
        'started_at', 'question_started_at', 'ended_at',
        'question_deadline_at', 'phase_started_at',
    ];

    protected function casts(): array
    {
        return [
            'question_index'       => 'integer',
            'started_at'           => 'datetime',
            'question_started_at'  => 'datetime',
            'ended_at'             => 'datetime',
            'question_deadline_at' => 'datetime',
            'phase_started_at'     => 'datetime',
        ];
    }

    public function quiz()
    {
        return $this->belongsTo(GameQuiz::class, 'quiz_id', 'uuid');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'uuid');
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'hosted_by', 'uuid');
    }

    public function currentQuestion()
    {
        return $this->belongsTo(GameQuestion::class, 'current_question_id', 'uuid');
    }

    public function participants()
    {
        return $this->hasMany(GameLiveParticipant::class, 'session_id', 'uuid');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['lobby', 'question', 'reveal', 'standings'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'lobby'     => 'Lobi',
            'question'  => 'Soal aktif',
            'reveal'    => 'Pembahasan',
            'standings' => 'Papan peringkat',
            'ended'     => 'Selesai',
            default     => 'Idle',
        };
    }
}
