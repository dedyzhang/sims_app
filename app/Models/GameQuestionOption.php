<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GameQuestionOption extends Model
{
    use HasUuids;

    protected $table = 'game_question_options';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'question_id', 'option_text', 'is_correct', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function question()
    {
        return $this->belongsTo(GameQuestion::class, 'question_id', 'uuid');
    }
}
