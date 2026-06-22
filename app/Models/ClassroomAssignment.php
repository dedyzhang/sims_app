<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassroomAssignment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'classroom_assignments';
    protected $primaryKey = 'uuid';
    protected $fillable = [
        'classroom_id', 'created_by', 'title', 'instructions', 'type', 'max_score',
        'allow_late', 'opens_at', 'due_at', 'status', 'scheduled_publish_at', 'hide_scores',
        'is_locked', 'access_token',
    ];

    protected function casts(): array
    {
        return [
            'allow_late'           => 'boolean',
            'opens_at'             => 'datetime',
            'due_at'               => 'datetime',
            'scheduled_publish_at' => 'datetime',
            'max_score'            => 'integer',
            'hide_scores'          => 'boolean',
            'is_locked'            => 'boolean',
        ];
    }

    public function lockEvents()
    {
        return $this->morphMany(ClassroomLockEvent::class, 'lockable');
    }

    /** Kelas asal (tempat dibuat). */
    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'uuid');
    }

    /** Semua kelas yang ditaut. */
    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_assignment_links', 'assignment_id', 'classroom_id', 'uuid', 'uuid');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function files()
    {
        return $this->hasMany(ClassroomAssignmentFile::class, 'assignment_id', 'uuid');
    }

    public function submissions()
    {
        return $this->hasMany(ClassroomSubmission::class, 'assignment_id', 'uuid');
    }

    public function comments()
    {
        return $this->morphMany(ClassroomComment::class, 'commentable');
    }

    public function isOpen(): bool
    {
        $now = now();
        if ($this->opens_at && $now->lt($this->opens_at)) return false;
        if ($this->due_at && $now->gt($this->due_at)) return $this->allow_late;
        return $this->status === 'published';
    }
}
