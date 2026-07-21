<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Siapa saja yang benar-benar membuka halaman satu sesi live — lihat migration utk detail. */
class GameLiveParticipant extends Model
{
    use HasUuids;

    protected $table = 'game_live_participants';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['session_id', 'user_id', 'joined_at', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'joined_at'    => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(GameLiveSession::class, 'session_id', 'uuid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
}
