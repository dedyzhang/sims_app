<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GameAttempt extends Model
{
    use HasUuids;

    protected $table = 'game_attempts';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    public const SOURCE_ASYNC = 'async';
    public const SOURCE_LIVE = 'live';

    protected $fillable = [
        'assignment_id', 'student_id', 'source', 'total_score', 'correct_count',
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

    public function assignment()
    {
        return $this->belongsTo(GameQuizAssignment::class, 'assignment_id', 'uuid');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id', 'uuid');
    }

    public function answers()
    {
        return $this->hasMany(GameAnswer::class, 'attempt_id', 'uuid');
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'graded'], true);
    }
}
