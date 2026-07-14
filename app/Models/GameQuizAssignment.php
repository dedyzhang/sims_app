<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GameQuizAssignment extends Model
{
    use HasUuids;

    protected $table = 'game_quiz_assignments';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'quiz_id', 'classroom_id', 'opens_at', 'due_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'due_at'   => 'datetime',
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

    public function attempts()
    {
        return $this->hasMany(GameAttempt::class, 'assignment_id', 'uuid');
    }
}
