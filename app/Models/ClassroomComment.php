<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassroomComment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'classroom_comments';
    protected $primaryKey = 'uuid';
    protected $fillable = ['commentable_type', 'commentable_id', 'classroom_id', 'user_id', 'parent_id', 'body'];

    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function parent()
    {
        return $this->belongsTo(ClassroomComment::class, 'parent_id', 'uuid');
    }

    public function replies()
    {
        return $this->hasMany(ClassroomComment::class, 'parent_id', 'uuid')->with('user')->orderBy('created_at');
    }
}
