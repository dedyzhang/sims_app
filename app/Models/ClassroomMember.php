<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClassroomMember extends Model
{
    use HasUuids;

    protected $table = 'classroom_members';
    protected $primaryKey = 'uuid';
    protected $fillable = ['classroom_id', 'user_id', 'role_in_class', 'joined_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'uuid');
    }
}
