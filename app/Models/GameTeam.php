<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GameTeam extends Model
{
    use HasUuids;

    protected $table = 'game_teams';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['quiz_id', 'classroom_id', 'name', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function quiz()
    {
        return $this->belongsTo(GameQuiz::class, 'quiz_id', 'uuid');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'uuid');
    }

    public function members()
    {
        return $this->hasMany(GameTeamMember::class, 'team_id', 'uuid');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'game_team_members', 'team_id', 'user_id', 'uuid', 'uuid');
    }
}
