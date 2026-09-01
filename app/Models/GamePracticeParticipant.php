<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GamePracticeParticipant extends Model
{
    use HasUuids;

    protected $table = 'game_practice_participants';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'session_id', 'guest_name', 'claimed_role', 'guest_token', 'joined_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at'    => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(GamePracticeSession::class, 'session_id', 'uuid');
    }

    public function attempt()
    {
        return $this->hasOne(GamePracticeAttempt::class, 'participant_id', 'uuid');
    }
}
