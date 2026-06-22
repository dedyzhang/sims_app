<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ForumTopicRead extends Model
{
    use HasUuids;

    protected $table = 'forum_topic_reads';
    protected $primaryKey = 'uuid';
    protected $fillable = ['topic_id', 'user_id', 'last_read_at'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }
}
