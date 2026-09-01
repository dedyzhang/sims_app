<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GamePracticeAnswer extends Model
{
    use HasUuids;

    protected $table = 'game_practice_answers';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'attempt_id', 'question_id', 'selected_option_id', 'answer_text',
        'is_correct', 'points_awarded', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct'     => 'boolean',
            'points_awarded' => 'integer',
            'answered_at'    => 'datetime',
        ];
    }

    public function attempt()
    {
        return $this->belongsTo(GamePracticeAttempt::class, 'attempt_id', 'uuid');
    }

    public function question()
    {
        return $this->belongsTo(GameQuestion::class, 'question_id', 'uuid');
    }
}
