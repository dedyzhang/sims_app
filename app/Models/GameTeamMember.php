<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GameTeamMember extends Model
{
    use HasUuids;

    protected $table = 'game_team_members';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['team_id', 'user_id'];

    public function team()
    {
        return $this->belongsTo(GameTeam::class, 'team_id', 'uuid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
}
