<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumTopic extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'forum_topics';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'id_kelas', 'id_pelajaran', 'classroom_id', 'created_by', 'title', 'slug', 'body',
        'audience', 'category', 'is_pinned', 'is_locked',
        'replies_count', 'reactions_count', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned'        => 'boolean',
            'is_locked'        => 'boolean',
            'replies_count'    => 'integer',
            'reactions_count'  => 'integer',
            'last_activity_at' => 'datetime',
        ];
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'uuid');
    }

    public function pelajaran()
    {
        return $this->belongsTo(Pelajaran::class, 'id_pelajaran', 'uuid');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    /** Ruang Kelas yang menaungi topik ini (null = forum umum, perilaku lama tak berubah). */
    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'uuid');
    }

    public function comments()
    {
        return $this->hasMany(ForumComment::class, 'topic_id', 'uuid');
    }

    public function reactions()
    {
        return $this->hasMany(ForumReaction::class, 'topic_id', 'uuid');
    }

    public function reads()
    {
        return $this->hasMany(ForumTopicRead::class, 'topic_id', 'uuid');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
