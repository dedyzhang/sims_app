<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GameQuestion extends Model
{
    use HasUuids;

    protected $table = 'game_questions';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'quiz_id', 'type', 'question_text', 'points', 'sort_order', 'meta', 'explanation',
    ];

    protected function casts(): array
    {
        return [
            'points'     => 'integer',
            'sort_order' => 'integer',
            'meta'       => 'array',
        ];
    }

    public function quiz()
    {
        return $this->belongsTo(GameQuiz::class, 'quiz_id', 'uuid');
    }

    public function options()
    {
        return $this->hasMany(GameQuestionOption::class, 'question_id', 'uuid')->orderBy('sort_order');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'true_false'   => 'Benar / Salah',
            'short_answer' => 'Isian singkat',
            'match'        => 'Pasangkan',
            default        => 'Pilihan ganda',
        };
    }
}
