<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumComment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'forum_comments';
    protected $primaryKey = 'uuid';

    protected $fillable = ['topic_id', 'user_id', 'parent_id', 'body', 'is_best_answer', 'edited_at'];

    protected function casts(): array
    {
        return [
            'is_best_answer' => 'boolean',
            'edited_at'      => 'datetime',
        ];
    }

    public function topic()
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id', 'uuid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function parent()
    {
        return $this->belongsTo(ForumComment::class, 'parent_id', 'uuid');
    }

    public function replies()
    {
        return $this->hasMany(ForumComment::class, 'parent_id', 'uuid');
    }

    public function reactions()
    {
        return $this->hasMany(ForumReaction::class, 'comment_id', 'uuid');
    }
}
