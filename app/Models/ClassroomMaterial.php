<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassroomMaterial extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'classroom_materials';
    protected $primaryKey = 'uuid';
    protected $fillable = [
        'classroom_id', 'uploaded_by', 'title', 'description', 'body', 'link_url', 'meet_url',
        'is_published', 'is_locked', 'access_token', 'published_at', 'scheduled_publish_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published'         => 'boolean',
            'is_locked'            => 'boolean',
            'published_at'         => 'datetime',
            'scheduled_publish_at' => 'datetime',
            'sort_order'           => 'integer',
        ];
    }

    public function lockEvents()
    {
        return $this->morphMany(ClassroomLockEvent::class, 'lockable');
    }

    /** Kelas asal (tempat dibuat) — untuk breadcrumb. */
    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'uuid');
    }

    /** Semua kelas yang ditaut (satu materi bisa tampil di banyak kelas). */
    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_material_links', 'material_id', 'classroom_id', 'uuid', 'uuid');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'uuid');
    }

    public function files()
    {
        return $this->hasMany(ClassroomMaterialFile::class, 'material_id', 'uuid')->orderBy('sort_order');
    }

    public function comments()
    {
        return $this->morphMany(ClassroomComment::class, 'commentable');
    }
}
