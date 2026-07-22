<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Log siswa meninggalkan mode fokus Arena (fullscreen / pindah tab). */
class GameFocusEvent extends Model
{
    use HasUuids;

    protected $table = 'game_focus_events';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'quiz_id', 'classroom_id', 'student_id', 'context',
        'attempt_id', 'session_id', 'type', 'reason',
    ];

    public function quiz()
    {
        return $this->belongsTo(GameQuiz::class, 'quiz_id', 'uuid');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id', 'uuid');
    }

    public function attempt()
    {
        return $this->belongsTo(GameAttempt::class, 'attempt_id', 'uuid');
    }
}
