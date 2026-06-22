<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ForumReaction extends Model
{
    use HasUuids;

    protected $table = 'forum_reactions';
    protected $primaryKey = 'uuid';
    protected $fillable = ['comment_id', 'topic_id', 'user_id', 'type'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function topic()
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id', 'uuid');
    }

    public function comment()
    {
        return $this->belongsTo(ForumComment::class, 'comment_id', 'uuid');
    }
}
